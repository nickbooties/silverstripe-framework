<?php
require_once 'Zend/Log.php';

/**
 * Wrapper class for a logging handler like {@link Zend_Log}
 * which takes a message (or a map of context variables) and
 * sends it to one or more {@link Zend_Log_Writer_Abstract}
 * subclasses for output.
 * 
 * <h1>Logging</h1>
 * 
 * <code>
 * SS_Log::log('My notice event'); // as string, no priority (defaults to SS_Log::NOTICE)
 * SS_log::log('My error event', SS_Log::ERR); // as string, with priority
 * SS_log::log(new Exception('My error event'), SS_Log::ERR); // as exception (includes backtrace)
 * </code>
 * 
 * Some of the more common priorities are SS_Log::ERR, SS_Log::WARN and SS_Log::NOTICE.
 * 
 * <h1>Writers</h1>
 * 
 * You can add an error writer by calling {@link SS_Log::add_writer()}
 * 
 * Example usage of logging errors by email notification:
 * <code>
 * SS_Log::add_writer(new SS_LogEmailWriter('my@email.com'), SS_Log::ERR);
 * </code>
 * 
 * Example usage of logging errors by file:
 * <code>
 *	SS_Log::add_writer(new SS_LogFileWriter('/var/log/silverstripe/errors.log'), SS_Log::ERR);
 * </code>
 *
 * Example usage of logging at warnings and errors by setting the priority to '<=':
 * <code>
 * SS_Log::add_writer(new SS_LogEmailWriter('my@email.com'), SS_Log::WARN, '<=');
 * </code>
 *	
 * Each writer object can be assigned a formatter. The formatter is
 * responsible for formatting the message before giving it to the writer.
 * {@link SS_LogErrorEmailFormatter} is such an example that formats errors
 * into HTML for human readability in an email client.
 * 
 * Formatters are added to writers like this:
 * <code>
 * $logEmailWriter = new SS_LogEmailWriter('my@email.com');
 * $myEmailFormatter = new MyLogEmailFormatter();
 * $logEmailWriter->setFormatter($myEmailFormatter);
 * </code>
 * 
 * @package sapphire
 * @subpackage dev
 */
class SS_Log {

	const EMERG   = Zend_Log::EMERG;  // Emergency: system is unusable
	const ALERT   = Zend_Log::ALERT;  // Alert: action must be taken immediately
	const CRIT    = Zend_Log::CRIT;  // Critical: critical conditions
	const ERR     = Zend_Log::ERR;  // Error: error conditions
	const WARN    = Zend_Log::WARN;  // Warning: warning conditions
	const NOTICE  = Zend_Log::NOTICE;  // Notice: normal but significant condition
	const INFO    = Zend_Log::INFO;  // Informational: informational messages
	const DEBUG   = Zend_Log::DEBUG;  // Debug: debug messages

	/**
	 * Logger class to use.
	 * @see SS_Log::get_logger()
	 * @var string
	 */
	public static $logger_class = 'SS_ZendLog';

	/**
	 * @see SS_Log::get_logger()
	 * @var object
	 */
	protected static $logger;

	/**
	 * Get the logger currently in use, or create a new
	 * one if it doesn't exist.
	 * 
	 * @return object
	 */
	public static function get_logger() {
		if(!self::$logger) {
			self::$logger = new self::$logger_class;
		}
		return self::$logger;
	}

	/**
	 * Get all writers in use by the logger.
	 * @return array Collection of Zend_Log_Writer_Abstract instances
	 */
	public static function get_writers() {
		return self::get_logger()->getWriters();
	}

	/**
	 * Remove all writers currently in use.
	 */
	public static function clear_writers() {
		self::get_logger()->clearWriters();
	}

	/**
	 * Remove a writer instance from the logger.
	 * @param object $writer Zend_Log_Writer_Abstract instance
	 */
	public static function remove_writer($writer) {
		self::get_logger()->removeWriter($writer);
	}

	/**
	 * Add a writer instance to the logger.
	 * @param object $writer Zend_Log_Writer_Abstract instance
	 * @param const $priority Priority. Possible values: SS_Log::ERR, SS_Log::WARN or SS_Log::NOTICE
	 * @param $comparison Priority comparison operator.  Acts on the integer values of the error
	 * levels, where more serious errors are lower numbers.  By default this is "=", which means only
	 * the given priority will be logged.  Set to "<=" if you want to track errors of *at least* 
	 * the given priority.
	 */
	public static function add_writer($writer, $priority = null, $comparison = '=') {
		if($priority) $writer->addFilter(new Zend_Log_Filter_Priority($priority, $comparison));
		self::get_logger()->addWriter($writer);
	}

	/**
	 * Dispatch a message by priority level.
	 * 
	 * The message parameter can be either a string (a simple error
	 * message), or an array of variables. The latter is useful for passing
	 * along a list of debug information for the writer to handle, such as
	 * error code, error line, error context (backtrace).
	 *
	 * If the $message paramter is passed as an array, the following keys are supported:
	 * - 'errno': Custom error number (optional)
	 * - 'errstr': Error message (required)
	 * - 'errfile': File where the event occurred (optional)
	 * - 'errline' => Line where the event occurred (optional)
	 * - 'errcontext': Backtrace information (optional)
	 *
	 * When passing $message as an exception object, event data like backtrace or file will be auto-populated.
	 * When passing as a string, only the 'errstr' event data will be filled out.
	 * 
	 * @param mixed $message Exception object, array of error context variables, or a string.
	 * @param const $priority Priority. Possible values: SS_Log::ERR, SS_Log::WARN, SS_Log::NOTICE 
	 *  (see class constats for full list). Defaults to SS_Log::NOTICE
	 */
	public static function log($message, $priority = null) {
		if(!$priority) $priority = SS_Log::NOTICE;

		if($message instanceof Exception) {
			$message = array(
				'errno' => '',
				'errstr' => $message->getMessage(),
				'errfile' => $message->getFile(),
				'errline' => $message->getLine(),
				'errcontext' => $message->getTrace()
			);
		} elseif(is_string($message)) {
			$trace = debug_backtrace();
			array_pop($trace); // remove the log() call itself
			$message = array(
				'errno' => '',
				'errstr' => $message,
				'errfile' => $trace[0]['file'],
				'errline' => $trace[0]['line'],
				'errcontext' => $trace
			);
		}
		try {
			self::get_logger()->log($message, $priority);
		} catch(Exception $e) {
			// @todo How do we handle exceptions thrown from Zend_Log?
			// For example, an exception is thrown if no writers are added
		}
	}

}