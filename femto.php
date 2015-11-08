<?php

// Load the exceptions that we are going to use
require __DIR__ . '/exceptions.php';

/**
 * Femto Framework
 *
 * @version 0.2.4
 * @author James White <dev.jameswhite@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

class Femto
{
	/**
	 * Holds the name of the page is being displayed
	 * @var string
	 */
	public $page;

	/**
	 * A string representing the template being used or not if that's the case
	 * @var string
	 */
	public $template;

	/**
	 * An array of variables to be passed to the template
	 * @var array
	 */
	private $_template_vars;

	/**
	 * The buffered page content that will be passed to a template if one is present
	 * @var string
	 */
	private $_template_content;

	/**
	 * Holds config arrays that have been requested using getConfig
	 * @var array
	 */
	private $_config = array();

	/**
	 * Directory path of the application route, this needs to be passed in
	 * @var string
	 */
	private $_app_root;

	/**
	 * Holds the cleaned requset uri
	 * @var string
	 */
	private $_request_uri;

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

		try {
			// Load the page, if one isn't specified in the request load the index page
			$this->_request_uri = str_replace(array('?', $_SERVER['QUERY_STRING']), '', $_SERVER['REQUEST_URI']);
			$this->_loadPage(trim($this->_request_uri === '/' ? 'index' : $this->_request_uri, '/'));
		} catch (FemtoPageNotFoundException $e) {
			// Set a 404 header because we couldn't find the page
			header('HTTP/1.1 404 Not Found');

			// We need to reset the template state
			$this->_resetTemplate();

			// Make sure the 404 is maintained if the 404 page doesn't exist
			try {
				$this->_loadPage('404');
			} catch (Exception $e) {}
		} catch (Exception $e) {
			// Discard the output buffer, we don't want to display content
			// if an exception was caught
			ob_end_clean();

			// Let the world know that you messed up
			header('HTTP/1.1 500 Internal Server Error');

			// We need to reset the template state
			$this->_resetTemplate();

			// Load the 500 page, if this fails it doesn't really make any difference
			$this->_loadPage('500', array('e' => $e));
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
			$config = $this->_loadFile($type, 'config');

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

		throw new FemtoException("Unable to locate config variable '{$variable}' of type '{$type}' in file " . $this->_getFilePath($type, 'config'));
	}

	/**
	 * Can be called from pages and fragments to load a fragment directly in place
	 *
	 * @param string $name The fragment name
	 * @param array $variables An associative array of vars to pass to the fragment.
	 * Passing array('a' => 'b') will result in $a = 'b' in the fragment
	 * @return void
	 */
	public function useFragment($name, $variables = false)
	{
		$this->_loadFile($name, 'fragment', $variables);
	}

	/**
	 * Can be called from pages to specifiy a template that they want to use
	 * @param string $name The template name
	 * @param array $variables an associative array of variables to be passed to the template.
	 * Passing array('a' => 'b') will result in $a = 'b' in the fragment
	 * @return void
	 */
	public function useTemplate($name, $variables = false)
	{
		$this->template = $name;
		$this->_template_vars = $variables ? $variables : array();
	}

	/**
	 * Retrieves and displays the template content
	 * @throws FemtoException if no template is set
	 * @return string $template_content
	 */
	public function templateContent()
	{
		if ($this->template === null) {
			throw new FemtoException("No template has been set so you can't get any content for it!");
		}

		echo $this->_template_content;
	}

	/**
	 * Returns a part of the url or null if it doesn't exist
	 * @param int $part_number 1 indexed url part number e.g /a/b a is part 1 and b is part 2
	 * @return string|null the url part
	 */
	public function getUrlPart($part_number)
	{
		// Split the url into an array by slashes
		$request_uri = array_filter(explode('/', $this->_request_uri), function($part) {
			return !empty($part);
		});

		return !empty($request_uri[$part_number]) ? $request_uri[$part_number] : null;
	}

	/**
	 * Tries to load a page
	 *
	 * @param string $page The name of the file in the pages dir excluding the extension
	 * @param array $variables An associative array of vars to pass to the file passing
	 * array('a' => 'b') will result in $a = 'b' in the page
	 * @return void
	 */
	private function _loadPage($page, $variables = false)
	{
		// Start the output buffer captain
		ob_start();

		// Store the page
		$this->page = $page;

		// Load the page that was requested
		$this->_loadFile($this->page, 'page', $variables);

		// If a template was set we need to get the page content and pass it to the template
		if ($this->template !== null) {
			// Set the template content var
			$this->_template_content = ob_get_clean();

			// Restart the buffer
			ob_start();

			// Load the template with any template vars that were passed in earlier
			// from the page
			$this->_loadFile($this->template, 'template', $this->_template_vars);
		}

		// And finally output everything in the buffer
		ob_end_flush();
	}

	/**
	 * Pretty self explanatory, helper to reset template state
	 *
	 * @return void
	 */
	private function _resetTemplate()
	{
		$this->template = null;
		$this->_template_vars = null;
	}

	/**
	 * Helper function to return the file path for a file of a given type
	 *
	 * @param string $file The file name
	 * @param int The file type, one of page, template, fragment, config
	 * @throws FemtoException if the file is not one of page, template, fragment, config
	 * @return string The file path
	 */
	private function _getFilePath($file, $type)
	{
		// Validate the type of file is supported
		if (!in_array($type, array('page', 'template', 'fragment', 'config'))) {
			throw new FemtoException("The type of file '{$type}' is not supported by Femto");
		}

		// Stop people trying anything clever with file paths
		$file = str_replace('..', '', $file);

		// Pluralise the type unless it's a config
		$type = $type === 'config' ? $type : "{$type}s";

		// Return the appropriate file path
		return "{$this->_app_root}/{$type}/{$file}.php";
	}

	/**
	 * Private function used to try and load files of the given type and if appropriate
	 * inject the given $variables into it
	 *
	 * @param string $file The file name
	 * @param int The file type, use one of page, template, fragment, configs
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
			// Make sure we are using the correct exception type!
			$exception_class = 'Femto' . ucfirst($type) . 'NotFoundException';

			// If not then we need to throw the appropriate exception type
			throw new $exception_class("Unable to locate the requested {$type} at path '{$this->_temp_path}'");
		}

		// Check if any variables were passed in, if so extract them for use in the template
		if ($variables !== false and is_array($variables)) {
			extract($variables);
		}

		// Tidy up a bit after ourselves
		unset($variables, $type, $file, $exception_class);

		return require $this->_temp_path;
	}
}
