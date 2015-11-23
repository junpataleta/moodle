<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * SOAP web service implementation classes and methods.
 *
 * @package    webservice_soap
 * @copyright  2009 Petr Skodak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/webservice/soap/classes/wsdl.php');

/**
 * SOAP service server implementation.
 *
 * @package    webservice_soap
 * @copyright  2009 Petr Skodak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.0
 */
class webservice_soap_server extends webservice_base_server {

    /** @var moodle_url The server URL. */
    protected $serverurl;

    /** @var  SoapServer The Soap */
    protected $soapserver;

    /** @var  string The response. */
    protected $response;

    /** @var  string The class name of the virtual class generated for this web service. */
    protected $serviceclass;

    /** @var bool WSDL mode flag. */
    protected $wsdlmode = false;

    /** @var \webservice_soap\webservice_soap_wsdl The object for WSDL generation. */
    protected $wsdl;

    /**
     * Contructor.
     *
     * @param string $authmethod authentication method of the web service (WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN, ...)
     */
    public function __construct($authmethod) {
        parent::__construct($authmethod);
         // Must not cache wsdl - the list of functions is created on the fly.
        ini_set('soap.wsdl_cache_enabled', '0');
        $this->wsname = 'soap';
    }

    /**
     * This method parses the $_POST and $_GET superglobals and looks for the following information:
     * - User authentication parameters:
     *   - Username + password (wsusername and wspassword), or
     *   - Token (wstoken)
     */
    protected function parse_request() {
        // Retrieve and clean the POST/GET parameters from the parameters specific to the server.
        parent::set_web_service_call_settings();

        // Get GET and POST parameters.
        $methodvariables = array_merge($_GET, $_POST);

        if ($this->authmethod == WEBSERVICE_AUTHMETHOD_USERNAME) {
            $this->username = isset($methodvariables['wsusername']) ? $methodvariables['wsusername'] : null;
            unset($methodvariables['wsusername']);
            $this->password = isset($methodvariables['wspassword']) ? $methodvariables['wspassword'] : null;
            unset($methodvariables['wspassword']);

            if (!$this->username or !$this->password) {
                // Workaround for the trouble with & in soap urls.
                $authdata = get_file_argument();
                $authdata = explode('/', trim($authdata, '/'));
                if (count($authdata) == 2) {
                    list($this->username, $this->password) = $authdata;
                }
            }
            $this->serverurl = new moodle_url('/webservice/soap/simpleserver.php/' . $this->username . '/' . $this->password);
        } else {
            $this->token = isset($methodvariables['wstoken']) ? $methodvariables['wstoken'] : null;
            unset($methodvariables['wstoken']);

            $this->serverurl = new moodle_url('/webservice/soap/server.php');
            $this->serverurl->param('wstoken', $this->token);
        }

        if (!empty($methodvariables['wsdl'])) {
            $this->wsdlmode = true;
            $this->serverurl->remove_params(['wsdl']);
        }
    }

    /**
     * Runs the SOAP web service.
     *
     * @throws coding_exception
     * @throws moodle_exception
     * @throws webservice_access_exception
     */
    public function run() {
        // We will probably need a lot of memory in some functions.
        raise_memory_limit(MEMORY_EXTRA);

        // Set some longer timeout since operations may need longer time to finish.
        external_api::set_timeout();

        // Set up exception handler.
        set_exception_handler(array($this, 'exception_handler'));

        // Init all properties from the request data.
        $this->parse_request();

        // Authenticate user, this has to be done after the request parsing. This also sets up $USER and $SESSION.
        $this->authenticate_user();

        // Make a list of all functions user is allowed to execute.
        $this->init_service_class();

        // Log the web service request.
        $params = array(
            'other' => array(
                'function' => 'unknown'
            )
        );
        $event = \core\event\webservice_function_called::create($params);
        $logdataparams = array(SITEID, 'webservice_soap', '', '', $this->serviceclass.' '.getremoteaddr(), 0, $this->userid);
        $event->set_legacy_logdata($logdataparams);
        $event->trigger();

        // Handle the SOAP request.
        $this->handle();

        // Session cleanup.
        $this->session_cleanup();
        die;
    }

    /**
     * Handles the web service function call.
     */
    protected function handle() {
        if ($this->wsdlmode) {
            // Get the response.
            $this->response = $this->wsdl->to_xml();

            // Send the results back in correct format.
            $this->send_response();
        } else {
            $wsdlurl = clone($this->serverurl);
            $wsdlurl->param('wsdl', 1);

            $options = array(
                'uri' => $this->serverurl->out(false)
            );
            // Initialise the SOAP server.
            $this->soapserver = new SoapServer($wsdlurl->out(false), $options);
            if (!empty($this->serviceclass)) {
                $this->soapserver->setClass($this->serviceclass);
                // Get all the methods for the generated service class then register to the SOAP server.
                $functions = get_class_methods($this->serviceclass);
                $this->soapserver->addFunction($functions);
            }

            // Get soap request from raw POST data.
            $soaprequest = file_get_contents('php://input');
            // Handle the request.
            try {
                $this->soapserver->handle($soaprequest);
            } catch (Exception $e) {
                $this->fault($e);
            }
        }
    }

    /**
     * Send the error information to the WS client formatted as an XML document.
     *
     * @param exception $ex the exception to send back
     */
    protected function send_error($ex = null) {
        if ($ex) {
            $info = $ex->getMessage();
            if (debugging() and isset($ex->debuginfo)) {
                $info .= ' - '.$ex->debuginfo;
            }
        } else {
            $info = 'Unknown error';
        }

        // Initialise new DOM document object.
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Fault node.
        $fault = $dom->createElement('SOAP-ENV:Fault');
        // Faultcode node.
        $fault->appendChild($dom->createElement('faultcode', 'MOODLE:error'));
        // Faultstring node.
        $fault->appendChild($dom->createElement('faultstring', $info));

        // Body node.
        $body = $dom->createElement('SOAP-ENV:Body');
        $body->appendChild($fault);

        // Envelope node.
        $envelope = $dom->createElement('SOAP-ENV:Envelope');
        $envelope->setAttribute('xmlns:SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');
        $envelope->appendChild($body);
        $dom->appendChild($envelope);

        // Send headers.
        $this->send_headers();

        // Output the XML.
        echo $dom->saveXML();
    }

    /**
     * Generates a struct class.
     *
     * @param external_single_structure $structdesc The basis of the struct class to be generated.
     * @param array $wsdlparams Contains the struct class' attributes and their respective types. To be used for WSDL generation.
     * @return string The class name of the generated struct class.
     */
    protected function generate_simple_struct_class(external_single_structure $structdesc, array &$wsdlparams = null) {
        global $USER;

        $fields = array();
        foreach ($structdesc->keys as $name => $fieldsdesc) {
            $type = $this->get_phpdoc_type($fieldsdesc);
            $wsdlparamtype = array('type' => $type);
            if (empty($fieldsdesc->allownull) || $fieldsdesc->allownull == NULL_ALLOWED) {
                $wsdlparamtype['nillable'] = true;
            }
            $wsdlparams[$name] = $wsdlparamtype;
            $fields[] = '    /** @var ' . $type . ' $' . $name . '*/';
            $fields[] = '    public $' . $name .';';
        }
        $fieldsstr = implode("\n", $fields);

        // We do this after the call to get_phpdoc_type() to avoid duplicate class creation.
        $classname = 'webservices_struct_class_000000';
        while (class_exists($classname)) {
            $classname++;
        }
        $code = <<<EOD
/**
 * Virtual struct class for web services for user id $USER->id in context {$this->restricted_context->id}.
 */
class $classname {
$fieldsstr
}
EOD;
        // Load into memory.
        eval($code);

        return $classname;
    }

    /**
     * Send the result of function call to the WS client.
     */
    protected function send_response() {
        $this->send_headers();
        echo $this->response;
    }

    /**
     * Internal implementation - sending of page headers.
     */
    protected function send_headers() {
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . ' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
        header('Content-Length: ' . count($this->response));
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: inline; filename="response.xml"');
    }

    /**
     * Load virtual class needed for the web service WSDL.
     *
     * The virtual class contains the web service functions that the user is allowed to use.
     * The web service function will be available if the user:
     * - is validly registered in the external_services_users table.
     * - has the required capability.
     * - meets the IP restriction requirement.
     */
    protected function init_service_class() {
        global $USER, $DB;

        $params = array();
        $wscond1 = '';
        $wscond2 = '';
        if ($this->restricted_serviceid) {
            $params = array('sid1' => $this->restricted_serviceid, 'sid2' => $this->restricted_serviceid);
            $wscond1 = 'AND s.id = :sid1';
            $wscond2 = 'AND s.id = :sid2';
        }

        $sql = "SELECT s.*, NULL AS iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 0)
                 WHERE s.enabled = 1 $wscond1

                 UNION

                SELECT s.*, su.iprestriction
                  FROM {external_services} s
                  JOIN {external_services_functions} sf ON (sf.externalserviceid = s.id AND s.restrictedusers = 1)
                  JOIN {external_services_users} su ON (su.externalserviceid = s.id AND su.userid = :userid)
                 WHERE s.enabled = 1 AND (su.validuntil IS NULL OR su.validuntil < :now) $wscond2";
        $params = array_merge($params, array('userid' => $USER->id, 'now' => time()));

        $serviceids = array();
        $remoteaddr = getremoteaddr();

        // Query list of external services for the user.
        $rs = $DB->get_recordset_sql($sql, $params);

        // Check which service ID to include.
        foreach ($rs as $service) {
            if (isset($serviceids[$service->id])) {
                continue; // Service already added.
            }
            if ($service->requiredcapability and !has_capability($service->requiredcapability, $this->restricted_context)) {
                continue; // Cap required, sorry.
            }
            if ($service->iprestriction and !address_in_subnet($remoteaddr, $service->iprestriction)) {
                continue; // Wrong request source ip, sorry.
            }
            $serviceids[$service->id] = $service->id;
        }
        $rs->close();

        // Generate the virtual class name.
        $classname = 'webservices_virtual_class_000000';
        while (class_exists($classname)) {
            $classname++;
        }
        $this->serviceclass = $classname;

        // Get the list of all available external functions.
        $wsmanager = new webservice();
        $functions = $wsmanager->get_external_functions($serviceids);

        // Initialise WSDL.
        $this->wsdl = new \webservice_soap\webservice_soap_wsdl($this->serviceclass, $this->serverurl);

        // Generate code for the virtual methods for this web service.
        $methods = '';
        foreach ($functions as $function) {
            $methods .= $this->get_virtual_method_code($function);
        }

        $code = <<<EOD
/**
 * Virtual class web services for user id $USER->id in context {$this->restricted_context->id}.
 */
class $classname {
$methods
}
EOD;
        // Load the virtual class definition into memory.
        eval($code);
    }

    /**
     * Returns a virtual method code for a web service function.
     *
     * @param stdClass $function a record from external_function
     * @return string The PHP code of the virtual method.
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_virtual_method_code($function) {
        $function = external_function_info($function);

        // Parameters and their defaults for the method signature.
        $paramanddefaults = array();
        // Parameters for external lib call.
        $params = array();
        $paramdesc = array();
        // The method's input parameters and their respective types.
        $wsdlin = array();
        // The method's output parameters and their respective types.
        $wsdlout = array();

        foreach ($function->parameters_desc->keys as $name => $keydesc) {
            $param = '$' . $name;
            $paramanddefault = $param;
            if ($keydesc->required == VALUE_OPTIONAL) {
                // It does not make sense to declare a parameter VALUE_OPTIONAL. VALUE_OPTIONAL is used only for array/object key.
                throw new moodle_exception('erroroptionalparamarray', 'webservice', '', $name);
            } else if ($keydesc->required == VALUE_DEFAULT) {
                // Need to generate the default, if there is any.
                if ($keydesc instanceof external_value) {
                    if ($keydesc->default === null) {
                        $paramanddefault .= ' = null';
                    } else {
                        switch ($keydesc->type) {
                            case PARAM_BOOL:
                                $default = (int)$keydesc->default;
                                break;
                            case PARAM_INT:
                                $default = $keydesc->default;
                                break;
                            case PARAM_FLOAT;
                                $default = $keydesc->default;
                                break;
                            default:
                                $default = "'$keydesc->default'";
                        }
                        $paramanddefault .= " = $default";
                    }
                } else {
                    // Accept empty array as default.
                    if (isset($keydesc->default) && is_array($keydesc->default) && empty($keydesc->default)) {
                        $paramanddefault .= ' = array()';
                    } else {
                        // For the moment we do not support default for other structure types.
                        throw new moodle_exception('errornotemptydefaultparamarray', 'webservice', '', $name);
                    }
                }
            }

            $params[] = $param;
            $paramanddefaults[] = $paramanddefault;
            $type = $this->get_phpdoc_type($keydesc);
            $wsdlin[$name]['type'] = $type;

            $paramdesc[] = '* @param ' . $type . ' $' . $name . ' ' . $keydesc->desc;
        }
        $paramanddefaults = implode(', ', $paramanddefaults);
        $paramdescstr = implode("\n ", $paramdesc);

        $serviceclassmethodbody = $this->service_class_method_body($function, $params);

        if (empty($function->returns_desc)) {
            $return = '* @return void';
        } else {
            $type = $this->get_phpdoc_type($function->returns_desc);
            $wsdlout['return']['type'] = $type;
            $return = '* @return ' . $type . ' ' . $function->returns_desc->desc;
        }

        // Register the method for the WSDL generation.
        $this->wsdl->register($function->name, $wsdlin, $wsdlout, $function->description);

        // Now create the virtual method that calls the ext implementation.
        $code = <<<EOD
/**
 * $function->description.
 *
 $paramdescstr
 $return
 */
public function $function->name($paramanddefaults) {
$serviceclassmethodbody
}
EOD;

        return $code;
    }

    /**
     * Get the phpdoc type for an external_description object.
     * external_value => int, double or string
     * external_single_structure => object|struct, on-fly generated stdClass name.
     * external_multiple_structure => array
     *
     * @param mixed $keydesc The type description.
     * @return string The PHP doc type of the external_description object.
     */
    protected function get_phpdoc_type($keydesc) {
        $type = null;
        if ($keydesc instanceof external_value) {
            switch ($keydesc->type) {
                case PARAM_BOOL: // 0 or 1 only for now.
                case PARAM_INT:
                    $type = 'int';
                    break;
                case PARAM_FLOAT;
                    $type = 'double';
                    break;
                default:
                    $type = 'string';
            }
        } else if ($keydesc instanceof external_single_structure) {
            $complextypeattrs = array();
            $classname = $this->generate_simple_struct_class($keydesc, $complextypeattrs);
            $type = $classname;
            $this->wsdl->add_complex_type($classname, $complextypeattrs);
        } else if ($keydesc instanceof external_multiple_structure) {
            $type = 'array';
        }

        return $type;
    }

    /**
     * Generates the method body of the virtual external function.
     *
     * @param stdClass $function a record from external_function.
     * @param array $params web service function parameters.
     * @return string body of the method for $function ie. everything within the {} of the method declaration.
     */
    protected function service_class_method_body($function, $params) {
        // Cast the param from object to array (validate_parameters except array only).
        $castingcode = '';
        $paramsstr = '';
        if (!empty($params)) {
            foreach ($params as $paramtocast) {
                // Clean the parameter from any white space.
                $paramtocast = trim($paramtocast);
                $castingcode .= "    $paramtocast = webservice_soap_server::cast_objects_to_array($paramtocast);\n";
            }
            $paramsstr = implode(', ', $params);
        }

        $descriptionmethod = $function->methodname . '_returns()';
        $callforreturnvaluedesc = $function->classname . '::' . $descriptionmethod;

        $methodbody = <<<EOD
$castingcode
    if ($callforreturnvaluedesc == null) {
        $function->classname::$function->methodname($paramsstr);
        return null;
    }
    return external_api::clean_returnvalue($callforreturnvaluedesc, $function->classname::$function->methodname($paramsstr));
EOD;
        return $methodbody;
    }

    /**
     * Recursive function to recurse down into a complex variable and convert all
     * objects to arrays.
     *
     * @param mixed $param value to cast
     * @return mixed Cast value
     */
    public static function cast_objects_to_array($param) {
        if (is_object($param)) {
            $param = (array)$param;
        }
        if (is_array($param)) {
            $toreturn = array();
            foreach ($param as $key => $value) {
                $toreturn[$key] = self::cast_objects_to_array($value);
            }
            return $toreturn;
        }
        return $param;
    }

    /**
     * Generate a server fault.
     *
     * Note that the parameter order is the reverse of SoapFault's constructor parameters.
     *
     * Moodle note: basically we return the faultactor (errorcode) and faultdetails (debuginfo).
     *
     * If an exception is passed as the first argument, its message and code
     * will be used to create the fault object.
     *
     * @link   http://www.w3.org/TR/soap12-part1/#faultcodes
     * @param  string|Exception $fault
     * @param  string $code SOAP Fault Codes
     */
    public function fault($fault = null, $code = 'Receiver') {
        $allowedfaultmodes = array(
            'VersionMismatch', 'MustUnderstand', 'DataEncodingUnknown',
            'Sender', 'Receiver', 'Server'
        );
        if (!in_array($code, $allowedfaultmodes)) {
            $code = 'Receiver';
        }

        // Intercept any exceptions and add the errorcode and debuginfo (optional).
        $actor = null;
        $details = null;
        $errorcode = 'unknownerror';
        $message = 'Unknown error';
        if ($fault instanceof Exception) {
            // Add the debuginfo to the exception message if debuginfo must be returned.
            $actor = isset($fault->errorcode) ? $fault->errorcode : null;
            $errorcode = $actor;
            if (debugging()) {
                $message = $fault->getMessage();
                $details = isset($fault->debuginfo) ? $fault->debuginfo : null;
            }
        } else if (is_string($fault)) {
            $message = $fault;
        }

        $this->soapserver->fault($code, $message . ' | ERRORCODE: ' . $errorcode, $actor, $details);
    }
}

/**
 * The Zend SOAP server but with a fault that returns debuginfo.
 *
 * @package    webservice_soap
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.2
 * @deprecated since 3.1, see {@link webservice_soap_server()}.
 */
class moodle_zend_soap_server extends webservice_soap_server {

    /**
     * moodle_zend_soap_server constructor.
     *
     * @param string $authmethod
     */
    public function __construct($authmethod) {
        debugging('moodle_zend_soap_server is deprecated, please use webservice_soap_server instead.', DEBUG_DEVELOPER);
        parent::__construct($authmethod);
    }

    /**
     * Generate a server fault.
     *
     * Note that the arguments are the reverse of those used by SoapFault.
     *
     * Moodle note: basically we return the faultactor (errorcode) and faultdetails (debuginfo).
     *
     * If an exception is passed as the first argument, its message and code
     * will be used to create the fault object if it has been registered via
     * {@Link registerFaultException()}.
     *
     * @link   http://www.w3.org/TR/soap12-part1/#faultcodes
     * @param  string|Exception $fault
     * @param  string $code SOAP Fault Codes
     * @return SoapFault
     * @deprecated since 3.1, see {@link webservice_soap_server::fault()}.
     */
    public function fault($fault = null, $code = "Receiver") {
        debugging('moodle_zend_soap_server::fault() is deprecated, please use webservice_soap_server::fault() instead.',
                DEBUG_DEVELOPER);
        parent::fault($fault = null, $code = "Receiver");
    }

    /**
     * Handle a request.
     *
     * NOTE: this is basically a copy of the Zend handle()
     *       but with $soap->fault returning faultactor + faultdetail
     *       So we don't require coding style checks within this method
     *       to keep it as similar as the original one.
     *
     * Instantiates SoapServer object with options set in object, and
     * dispatches its handle() method.
     *
     * $request may be any of:
     * - DOMDocument; if so, then cast to XML
     * - DOMNode; if so, then grab owner document and cast to XML
     * - SimpleXMLElement; if so, then cast to XML
     * - stdClass; if so, calls __toString() and verifies XML
     * - string; if so, verifies XML
     *
     * If no request is passed, pulls request using php:://input (for
     * cross-platform compatability purposes).
     *
     * @param DOMDocument|DOMNode|SimpleXMLElement|stdClass|string $request Optional request
     * @return void|string
     * @deprecated since 3.1, see {@link webservice_soap_server::handle()}.
     */
    public function handle($request = null) {
        debugging('moodle_zend_soap_server::handle() is deprecated, please use webservice_soap_server::handle() instead.',
            DEBUG_DEVELOPER);
        parent::handle();
    }
}

/**
 * SOAP test client class
 *
 * @package    webservice_soap
 * @copyright  2009 Petr Skodak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.0
 */
class webservice_soap_test_client implements webservice_test_client_interface {

    /**
     * Execute test client WS request
     *
     * @param string $serverurl server url (including token parameter or username/password parameters)
     * @param string $function function name
     * @param array $params parameters of the called function
     * @return mixed
     */
    public function simpletest($serverurl, $function, $params) {
        global $CFG;

        require_once($CFG->dirroot . '/webservice/soap/lib.php');
        $client = new webservice_soap_client($serverurl);
        return $client->call($function, $params);
    }
}
