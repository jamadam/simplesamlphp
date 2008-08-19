<?php

/**
 * Filter to generate a groups attribute based on many of the attributes of the user.
 *
 * By default this filter will generate groups from the following set of attributes:
 * - 'eduPersonAffiliation'
 * - 'eduPersonOrgUnitDN'
 * - 'eduPersonEntitlement'
 *
 * This can be overridden by specifying the names of the attributes in the configuration.
 *
 * It will attempt to determine a realm the user belongs to based on the eduPersonPrincipalName
 * attribute, if it is present.
 *
 * The groups this filter generates are on the form:
 * <attribute name>-<attributevalue> and <attributename>-<realm>-<attributevalue>.
 *
 *
 * Note that this filter isn't a drop-in replacement for the groups attributealter function. The
 * difference is that it uses the full attribute name, instead of shortening them to for example
 * affiliation, and it escapes illegal characters in a style similar to urlencoding. It also generates
 * groups both with and without a realm part. If no realm is determined, it will only generate attributes
 * without a realm-part.
 *
 *
 * Example - generate from default set of attributes:
 * <code>
 * 'authproc' => array(
 *   array('core:GenerateGroups'),
 *   ),
 * </code>
 *
 * Example - generate from only the eduPersonAffilitation attribute:
 * <code>
 * 'authproc' => array(
 *   array('core:GenerateGroups', 'eduPersonAffiliation'),
 *   ),
 * </code>
 *
 * @author Olav Morken, UNINETT AS.
 * @package simpleSAMLphp
 * @version $Id$
 */
class sspmod_core_Auth_Process_GenerateGroups extends SimpleSAML_Auth_ProcessingFilter {


	/**
	 * The attributes we should generate groups from.
	 */
	private $generateGroupsFrom;


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (count($config) === 0) {
			/* Use default groups. */
			$this->generateGroupsFrom = array(
				'eduPersonAffiliation',
				'eduPersonOrgUnitDN',
				'eduPersonEntitlement',
			);

		} else {
			/* Validate configuration. */
			foreach ($config as $attributeName) {
				if (!is_string($attributeName)) {
					throw new Exception('Invalid attribute name for core:GenerateGroups filter: ' .
						var_export($attributeName, TRUE));
				}
			}

			$this->generateGroupsFrom = $config;
		}
	}


	/**
	 * Apply filter to add groups attribute.
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		$groups = array();
		$attributes =& $request['Attributes'];

		$realm = self::getRealm($attributes);
		if ($realm !== NULL) {
			$groups[] = 'realm-' . $realm;
		}


		foreach ($this->generateGroupsFrom as $name) {
			if (!array_key_exists($name, $attributes)) {
				SimpleSAML_Logger::debug('GenerateGroups - attribute \'' . $name . '\' not found.');
				/* Attribute not present. */
				continue;
			}

			foreach ($attributes[$name] as $value) {
				$value = self::escapeIllegalChars($value);
				$groups[] = $name . '-' . $value;
				if ($realm !== NULL) {
					$groups[] = $name . '-' . $realm . '-' . $value;
				}
			}
		}

		if (count($groups) > 0) {
			$attributes['groups'] = $groups;
		}
	}


	/**
	 * Determine which realm the user belongs to.
	 *
	 * This function will attempt to determine the realm a user belongs to based on the
	 * eduPersonPrincipalName attribute if it is present. If it isn't, or if it doesn't contain
	 * a realm, NULL will be returned.
	 *
	 * @param array $attributes  The attributes of the user.
	 * @return string|NULL  The realm of the user, or NULL if we are unable to determine the realm.
	 */
	private static function getRealm($attributes) {
		assert('is_array($attributes)');

		if (!array_key_exists('eduPersonPrincipalName', $attributes)) {
			return NULL;
		}
		$eppn = $attributes['eduPersonPrincipalName'];

		if (count($eppn) < 1) {
			return NULL;
		}
		$eppn = $eppn[0];

		$realm = explode('@', $eppn, 2);
		if (count($realm) < 2) {
			return NULL;
		}
		$realm = $realm[1];

		return self::escapeIllegalChars($realm);
	}


	/**
	 * Escape special characters in a string.
	 *
	 * This function is similar to urlencode, but encodes many more characters.
	 * This function takes any characters not in [a-zA-Z0-9_@=.] and encodes them with as
	 * %<hex version>. For example, it will encode '+' as '%2b' and '%' as '%25'.
	 *
	 * @param string $string  The string which should be escaped.
	 * @return string  The escaped string.
	 */
	private static function escapeIllegalChars($string) {
		assert('is_string($string)');

		/* Since preg_replace escapes both ["] and ['], while either of them isn't unescaped in the string
		 * evaluation, we need a test to catch both of them.
		 */
		$replacement = '("\\1" === "\\\'") ? "%27" : sprintf("%%%02x", ord("\\1"))';
		return preg_replace('/([^a-zA-Z0-9_@=.])/e', $replacement, $string);
	}

}

?>