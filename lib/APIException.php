<?php

namespace Wireframe;

/**
 * Wireframe API exception
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class APIException extends \Exception {

    /**
     * HTTP response code
     *
     * @var int
     */
    protected $http_response_code = 500;

    /**
     * Constructor
     *
     * @param string|null $message
     * @param int $code
     * @param Exception|null $previous
     * @param int $http_response_code
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null, int $http_response_code = 500) {
        $this->http_response_code = $http_response_code;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get HTTP response code
     *
     * @return int
     */
    public function getResponseCode(): int {
        return $this->http_response_code;
    }

    /**
     * Set HTTP response code
     *
     * @param int $http_response_code
     * @return APIException Self-reference
     */
    public function setResponseCode(int $http_response_code): APIException {
        $this->http_response_code = $http_response_code;
        return $this;
    }

}
