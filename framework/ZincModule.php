<?php
abstract class ZincModule
{
	private $name, $lib;
	private $depends = array(), $includes = array(), $classes = array();
	protected $hasConfig = false;
	public $path;

	final function __construct($path, $lib)
	{
		//	assign in the paramamters
		$this->path = $path;
		$this->lib = $lib;
		$this->name = strtolower(str_replace('Module', '', get_class($this)));

		//	second stage (module specific) construction
		$this->init();

		$classname = get_class($this);
		//	load any dependant modules
		if($this->getDepends())
			foreach($this->getDepends() as $thisDepends)
				$this->lib->loadMod($thisDepends);

		//	include any normal files that need to be included
		if($this->getIncludes())
		{
			foreach($this->getIncludes() as $thisInclude)
			{
				if($thisInclude[0] == '/')
					require($thisInclude);
				else
					require($this->path . '/' . $thisInclude);
			}
		}

		//	register any class files
		if($classes = $this->getClasses())
			foreach($classes as $className => $classPath)
				ZoopLoader::addClass($className, $classPath);

		if($this->hasConfig)
			$this->loadConfig();

		//	handle configuration
		$this->configure();
	}

	protected function init() {}
	protected function configure() {}

	/**
	 * Figures out the name of the module by removing the word "Module" from
	 * the class name and returning the result
	 *
	 * @return string
	 */
	function createName()
	{
		return strtolower(str_replace('Module', '', get_class($this)));
	}

	function getConfigPath()
	{
		return $this->name;
	}

	private function loadConfig()
	{
		Config::suggest($this->path . '/' . 'config.yaml', 'zinc.' . $this->getConfigPath());
	}

	/**
	 * Returns the configuration options using the Config class.
	 * Returns config options from "zinc.<modulename>.<path>"
	 * Path is optional and may be omitted.
	 *
	 * @param string $path
	 * @return array of configuration options
	 */
	function getConfig($path = '')
	{
		$config = Config::get('zinc.' . $this->getConfigPath() . $path);
		return $config;
	}

	/**
	 * stuff about this function
	 *
	 * @return array(list of files to include) or false;
	 */
	protected function addClass($className, $classPath = null)
	{
		$this->classes[] = $className;
		if (!$classPath) {
			$classPath = $this->path . "/" . $className . ".php";
		}
		$this->classes[$className] = $classPath;
	}

	protected function getClasses()
	{
		return $this->classes;
	}

	protected function addInclude($include)
	{
		$this->includes[] = $include;
	}

	protected function getIncludes()
	{
		return $this->includes;
	}

	protected function depend($module)
	{
		$this->depends[] = $module;
	}

	private function getDepends()
	{
		return $this->depends;
	}
}
