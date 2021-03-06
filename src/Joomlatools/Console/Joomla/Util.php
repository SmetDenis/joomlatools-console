<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Util
{
    protected static $_versions  = array();

    /**
     * Retrieve the Joomla version.
     * Returns FALSE if unable to find correct version.
     *
     * @param string $base Base path for the Joomla installation
     * @return string|boolean
     */
    public static function getJoomlaVersion($base)
    {
        $key = md5($base);

        if (!isset(self::$_versions[$key]))
        {
            $code = self::buildTargetPath('/libraries/cms/version/version.php', $base);

            if (file_exists($code))
            {
                if (!defined('JPATH_PLATFORM')) {
                    define('JPATH_PLATFORM', self::buildTargetPath('/libraries', $base));
                }

                if (!defined('_JEXEC')) {
                    define('_JEXEC', 1);
                }

                $identifier = uniqid();

                $source = file_get_contents($code);
                $source = preg_replace('/<\?php/', '', $source, 1);
                $source = preg_replace('/class JVersion/i', 'class JVersion' . $identifier, $source);

                eval($source);

                $class   = 'JVersion'.$identifier;
                $version = new $class();

                $canonical = function($version) {
                    if (isset($version->RELEASE)) {
                        return 'v' . $version->RELEASE . '.' . $version->DEV_LEVEL;
                    }

                    // Joomla 3.5 and up uses constants instead of properties in JVersion
                    $className = get_class($version);
                    if (defined("$className::RELEASE")) {
                        return $version::RELEASE . '.' . $version::DEV_LEVEL;
                    }

                    return 'unknown';
                };

                self::$_versions[$key] = $canonical($version);
            }
            else self::$_versions[$key] = false;
        }
        
        return self::$_versions[$key];
    }

    /**
     * Checks if we are dealing with joomlatools/platform or not
     *
     * @param string $base Base path for the Joomla installation
     * @return boolean
     */
    public static function isPlatform($base)
    {
        $manifest = realpath($base . '/composer.json');

        if (file_exists($manifest))
        {
            $contents = file_get_contents($manifest);
            $package  = json_decode($contents);

            if ($package->name == 'joomlatools/platform') {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the full path for a given path inside a Joomla project.
     * If base is a Joomla Platform installation, the path will be
     * translated into the correct path in platform.
     *
     * Example: /administrator/components/com_xyz becomes /app/administrator/components/com_xyz in platform.
     * 
     * @param string $path The original relative path to the file/directory
     * @param string $base The root directory of the Joomla installation
     * @return string Target path
     */
    public static function buildTargetPath($path, $base = '')
    {
        if (!empty($base) && substr($base, -1) == '/') {
            $base = substr($base, 0, -1);
        }

        $path = str_replace($base, '', $path);

        if (substr($path, 0, 1) != '/') {
            $path = '/'.$path;
        }

        if (self::isPlatform($base))
        {
            $paths = array(
                '/administrator/manifests' => '/config/manifests/',
                '/administrator' => '/app/administrator',
                '/components'    => '/app/site/components',
                '/modules'       => '/app/site/modules',
                '/language'      => '/app/site/language',
                '/media'         => '/web/media',
                '/plugins'       => '/lib/plugins',
                '/libraries'     => '/lib/libraries',
                '/images'        => '/web/images',
                '/configuration.php' => '/config/configuration.php'
            );

            foreach ($paths as $original => $replacement)
            {
                if (substr($path, 0, strlen($original)) == $original)
                {
                    $path = $replacement . substr($path, strlen($original));
                    break;
                }
            }
        }

        return $base.$path;
    }

    /**
     * Determine if we are running from inside the Joomlatools Box environment.
     * Only boxes >= 1.4.0 can be recognized.
     *
     * @return boolean true|false
     */
    public static function isJoomlatoolsBox()
    {
        if (php_uname('n') === 'joomlatools') {
            return true;
        }

        // Support boxes that do not have the correct hostname set
        $user = exec('whoami');
        if (trim($user) == 'vagrant' && file_exists('/home/vagrant/scripts/dashboard/index.php'))
        {
            if (file_exists('/etc/varnish/default.vcl')) {
                return true;
            }
        }

        return false;
    }
}