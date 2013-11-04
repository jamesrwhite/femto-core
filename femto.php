<?php

// Load the exceptions that we are going to use
require __DIR__ . '/exceptions.php';

/**
 * Femto Framework
 *
 * @author James White <dev.jameswhite@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

class Femto
{
	/**
	 * Holds config arrays that have been requested using getConfig
	 * @var array
	 */
	private $_config = array();

	/**
	 * Holds the name of the page is being displayed
	 * @var string
	 */
	private $_page;

	/**
	 * Directory path of the application route, this needs to be passed in
	 * @var string
	 */
	private $_app_root;

	/**
	 * Used to indicate that the file type is a page
	 * @var int
	 */
	const FEMTO_PAGE = 1;

	/**
	 * Used to indicate that the file type is a fragment
	 * @var int
	 */
	const FEMTO_FRAGMENT = 2;

	/**
	 * Used to indicate that the file type is a config
	 * @var int
	 */
	const FEMTO_CONFIG = 3;

	/**
	 * Set the application root directory
	 * @param string $app_root
	 * @return void
	 */
	public function setAppRoot($app_root)
	{
		$this->_app_root = $app_root;
	}

	/**
	 * The Femto application launcher used in the index.php file
	 *
	 * @return void
	 */
	public function launch()
	{
		// Was the application route defined?
		if ($this->_app_root === null) {

			throw new FemtoException('You must define the application route first, do so by calling $femto->setAppRoot($dir)');

		}

		// Start the output buffer captain!
		ob_start();

		try {

			// Set the page, default to index for requests to /
			$this->_page = $_SERVER['REQUEST_URI'] === '/' ? 'index' : $_SERVER['REQUEST_URI'];

			// Load the page that was requested
			$this->_loadFile($this->_page, self::FEMTO_PAGE);

			// No errors? Ok, let's do this then!
			ob_flush();

		} catch (FemtoPageNotFoundException $e) {

			// Set a 404 header because we couldn't find the page
			header('HTTP/1.1 404 Not Found');
			
			// Make sure the 404 is maintained if the 404 fragment doesn't exist
			try {

				$this->useFragment('femto/404');

			} catch (Exception $e) {}

		} catch (Exception $e) {

			// Discard the output buffer, we don't want to display content
			// if an exception was caught
			ob_clean();

			// Let the world know that you messed up
			header('HTTP/1.1 500 Internal Server Error');

			// Load the 500 fragment, if this fails it doesn't really make any difference
			$this->useFragment('femto/500', array('e' => $e));

		}
	}

	/**
	 * Helper function used to retrieve config variables
	 *
	 * @param string $type The type of config, this maps directly to a file in /config
	 * @param string $variable The variable you want returned from the specified config file
	 * @throws FemtoException if the contents of the config file was not an array
	 * @throws FemtoException if the $variable could not be found in the config array
	 * @return mixed The config variable
	 */
	public function getConfig($type, $variable)
	{
		// Is the requested config file not already cached?
		if (!isset($this->_config[$type])) {

			// Um, load it
			$config = $this->_loadFile($type, self::FEMTO_CONFIG);

			// Check it's what we expect
			if (!is_array($config)) {

				throw new FemtoException("Unable to parse config of type '{$type}'");

			}

			// Cache the config
			$this->_config[$type] = $config;

		}

		// Can we find the requested variable in the config array?
		if (isset($this->_config[$type][$variable])) {

			return $this->_config[$type][$variable];

		}

		throw new FemtoException("Unable to locate config variable '{$variable}' of type '{$type}' in file " . $this->_getFilePath($type, self::FEMTO_CONFIG));
	}

	/**
	 * Can be called from pages and fragments to load a fragment directly in place
	 *
	 * @param string $name The fragment name
	 * @param array $variables An associative array of vars to pass to the fragment passing
	 * array('a' => 'b') will result in $a = 'b' in the fragment
	 * @return void
	 */
	public function useFragment($name, $variables = false)
	{
		$this->_loadFile($name, self::FEMTO_FRAGMENT, $variables);
	}

	/**
	 * Helper function to return the file path for a file of a given type
	 *
	 * @param string $file The file name
	 * @param int The file type, use one of the FEMTO_X constants for this
	 * @throws FemtoException if the file is not one of the FEMTO_X constants
	 * @return string The file path
	 */
	private function _getFilePath($file, $type)
	{
		// Validate the type of file is supported
		if (!in_array($type, array(self::FEMTO_PAGE, self::FEMTO_FRAGMENT, self::FEMTO_CONFIG))) {

			throw new FemtoException("The type of file '{$type}' is not supported by Femto");

		}

		// Stop people trying anything clever with file paths
		$file = str_replace('..', '', $file);

		// Return the appropriate file path
		switch ($type) {

			case self::FEMTO_PAGE:
				return "{$this->_app_root}/pages/{$file}.php";

			case self::FEMTO_FRAGMENT:
				return "{$this->_app_root}/fragments/{$file}.php";

			case self::FEMTO_CONFIG:
				return "{$this->_app_root}/config/{$file}.php";

		}
	}

	/**
	 * Private function used to try and load files of the given type and if appropriate
	 * inject the given $variables into it
	 *
	 * @param string $file The file name
	 * @param int The file type, use one of the FEMTO_X constants for this
	 * @param array $variables An associative array of vars to pass to the file passing
	 * array('a' => 'b') will result in $a = 'b' in the file
	 * @throws FemtoPageNotFoundException If the type is page and it could not be found
	 * @throws FemtoFragmentNotFoundException If the type is fragment and it could not be found
	 * @throws FemtoConfigNotFoundException If the type is config and it could not be found
	 * @return mixed The file contents
	 */
	private function _loadFile($file, $type, $variables = false)
	{
		// Get the file path, this also handles validating the type
		$this->_temp_path = $this->_getFilePath($file, $type);

		// First let's check that file actually exists!
		if (!file_exists($this->_temp_path)) {

			// If not then we need to throw the appropriate exception type
			switch ($type) {

				case self::FEMTO_PAGE:
					throw new FemtoPageNotFoundException("Unable to locate the requested page at path '{$this->_temp_path}'");

				case self::FEMTO_FRAGMENT:
					throw new FemtoFragmentNotFoundException("Unable to locate the requested fragment at path '{$this->_temp_path}'");

				case self::FEMTO_CONFIG:
					throw new FemtoConfigNotFoundException("Unable to locate the requested config at path '{$this->_temp_path}'");

			}

		}

		// Check if any variables were passed in, if so extract them for use in the template
		if ($variables !== false and is_array($variables)) {

			extract($variables);

		}

		// Tidy up a bit after ourselves
		unset($variables, $type, $file);

		return require $this->_temp_path;
	}
}
