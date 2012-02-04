<?php
/**
 * Podcast Manager for Joomla!
 *
 * @package     PodcastManager
 * @subpackage  com_podcastmanager
 *
 * @copyright   Copyright (C) 2011-2012 Michael Babker. All rights reserved.
 * @license     GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 *
 * Podcast Manager is based upon the ideas found in Podcast Suite created by Joe LeBlanc
 * Original copyright (c) 2005 - 2008 Joseph L. LeBlanc and released under the GPLv2 license
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');

/**
 * Podcast edit model class.
 *
 * @package     PodcastManager
 * @subpackage  com_podcastmanager
 * @since       1.8
 */
class PodcastManagerModelPodcast extends JModelForm
{
	/**
	 * Model context string.
	 *
	 * @var    string
	 * @since  1.8
	 */
	protected $context = 'com_podcastmanager.podcast';

	/**
	 * The item being pulled
	 *
	 * @var    object
	 * @since  1.8
	 */
	protected $item = null;

	/**
	 * Method to process the file through the getID3 library to extract key data
	 *
	 * @param   mixed  $data  The data object for the form
	 *
	 * @return  mixed  The processed data for the form.
	 *
	 * @since  1.8
	 */
	protected function fillMetaData($data)
	{
		jimport('getid3.getid3');
		define('GETID3_HELPERAPPSDIR', JPATH_PLATFORM . '/getid3');

		$filename = $_COOKIE['podManFile'];

		// Set the filename field (fallback for if session data doesn't retain)
		$data->filename = $_COOKIE['podManFile'];

		if (!preg_match('/^http/', $filename))
		{
			$filename = JPATH_ROOT . '/' . $filename;
		}
		$getID3 = new getID3;
		$getID3->setOption(array('encoding' => 'UTF-8'));
		$fileInfo = $getID3->analyze($filename);

		// Check if there's an error from getID3
		if (isset($fileInfo['error']))
		{
			foreach ($fileInfo['error'] as $error)
			{
				JError::raiseNotice('500', $error);
			}
		}

		// Check if there's a warning from getID3
		if (isset($fileInfo['warning']))
		{
			foreach ($fileInfo['warning'] as $warning)
			{
				JError::raiseWarning('500', $warning);
			}
		}

		if (isset($fileInfo['tags_html']))
		{
			$t = $fileInfo['tags_html'];
			$tags = isset($t['id3v2']) ? $t['id3v2'] : (isset($t['id3v1']) ? $t['id3v1'] : (isset($t['quicktime']) ? $t['quicktime'] : null));
			if ($tags)
			{
				// Set the title field
				if (isset($tags['title']))
				{
					$data->title = $tags['title'][0];
				}
				// Set the album field
				if (isset($tags['album']))
				{
					$data->itSubtitle = $tags['album'][0];
				}
				// Set the artist field
				if (isset($tags['artist']))
				{
					$data->itAuthor = $tags['artist'][0];
				}
			}
		}

		// Set the duration field
		if (isset($fileInfo['playtime_string']))
		{
			$data->itDuration = $fileInfo['playtime_string'];
		}
		return $data;
	}

	/**
	 * Abstract method for getting the form from the model.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.8
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_podcastmanager.podcast', 'podcast', array('control' => 'jform', 'load_data' => $loadData));
		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get an object.
	 *
	 * @param   integer  $id  The id of the object to get.
	 *
	 * @return  mixed  Object on success, false on failure.
	 *
	 * @since   1.8
	 */
	public function &getItem($id = null)
	{
		if ($this->item === null)
		{
			$this->item = false;

			if (empty($id))
			{
				$id = $this->getState('podcast.id');
			}

			// Get a level row instance.
			$table = JTable::getInstance('Podcast', 'PodcastManagerTable');

			// Attempt to load the row.
			if ($table->load($id))
			{
				// Check published state.
				if ($published = $this->getState('filter.published'))
				{
					if ($table->published != $published)
					{
						return $this->item;
					}
				}

				// Convert the JTable to a clean JObject.
				$properties = $table->getProperties(1);
				$this->item = JArrayHelper::toObject($properties, 'JObject');
			}
			elseif ($error = $table->getError())
			{
				$this->setError($error);
			}
		}

		return $this->item;
	}

	/**
	 * Get the return URL.
	 *
	 * @return  string  The return URL.
	 *
	 * @since   1.8
	 */
	public function getReturnPage()
	{
		return base64_encode($this->getState('return_page'));
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $name     The table name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $options  Configuration array for model. Optional.
	 *
	 * @return  JTable  A JTable object
	 *
	 * @since   1.8
	 */
	public function getTable($name = 'Podcast', $prefix = 'PodcastManagerTable', $options = array())
	{
		return JTable::getInstance($name, $prefix, $options);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.8
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_podcastmanager.edit.podcast.data', array());

		if (empty($data))
		{
			$data = $this->getItem();

			// If changing the selected file, process the new data through getID3
			if (isset($_COOKIE['podManFile']))
			{
				$data = $this->fillMetaData($data);
			}
		}
		return $data;
	}

	/**
	 * Method to auto-populate the model state.  Calling getState in this method will result in recursion.
	 *
	 * @return  void
	 *
	 * @since   1.8
	 */
	protected function populateState()
	{
		$app = JFactory::getApplication();
		$input = $app->input;

		// Load state from the request.
		$pk = $input->get('p_id', '', 'int');
		$this->setState('podcast.id', $pk);

		$feedId = $input->get('feedname', '', 'int');
		$this->setState('podcast.feedname', $feedId);

		$return = $input->get('return', null, 'base64');

		if (!JUri::isInternal(base64_decode($return)))
		{
			$return = null;
		}

		$this->setState('return_page', base64_decode($return));

		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);

		$this->setState('layout', $input->get('layout', '', 'cmd'));
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success, False on error.
	 *
	 * @since   1.8
	 */
	public function save($data)
	{
		// Initialise variables;
		$dispatcher = JDispatcher::getInstance();
		$table = $this->getTable();
		$key = $table->getKeyName();
		$pk = (!empty($data[$key])) ? $data[$key] : (int) $this->getState($this->getName() . '.id');
		$isNew = true;

		// Include the content plugins for the on save events.
		JPluginHelper::importPlugin('content');

		// Allow an exception to be thrown.
		try
		{
			// Load the row if saving an existing record.
			if ($pk > 0)
			{
				$table->load($pk);
				$isNew = false;
			}

			// Bind the data.
			if (!$table->bind($data))
			{
				$this->setError($table->getError());
				return false;
			}

			// Check the data.
			if (!$table->check())
			{
				$this->setError($table->getError());
				return false;
			}

			// Trigger the onContentBeforeSave event.
			$result = $dispatcher->trigger('onContentBeforeSave', array($this->option . '.' . $this->name, &$table, $isNew));
			if (in_array(false, $result, true))
			{
				$this->setError($table->getError());
				return false;
			}

			// Store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());
				return false;
			}

			// Clean the cache.
			$this->cleanCache();

			// Trigger the onContentAfterSave event.
			$dispatcher->trigger('onContentAfterSave', array($this->option . '.' . $this->name, &$table, $isNew));
		}
		catch (Exception $e)
		{
			$this->setError($e->getMessage());
			return false;
		}

		$pkName = $table->getKeyName();

		if (isset($table->$pkName))
		{
			$this->setState($this->getName() . '.id', $table->$pkName);
		}
		$this->setState($this->getName() . '.new', $isNew);

		return true;
	}
}
