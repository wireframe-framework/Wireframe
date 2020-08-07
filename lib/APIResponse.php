<?php

namespace Wireframe;

/**
 * Wireframe API Response
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class APIResponse {

    /**
     * Pretty print
     *
     * @var bool
     */
    protected $pretty = true;

    /**
     * Path for current response
     *
     * @var string
     */
    protected $path = '';

    /**
     * Args for current response
     *
     * @var array
     */
    protected $args = [];

    /**
     * Response message
     *
     * @var string
     */
    protected $message = '';

    /**
     * HTTP status code
     *
     * @var int
     */
    protected $status_code = 200;

    /**
     * Response data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Set response data
     *
     * @param array $data
     * @return APIResponse Self-reference
     */
    public function setData(array $data): APIResponse {
        $this->data = $data;
        return $this;
    }

    /**
     * Get response data
     *
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Set response message
     *
     * @param string $message
     * @return APIResponse Self-reference
     */
    public function setMessage(string $message): APIResponse {
        $this->message = $message;
        return $this;
    }

    /**
     * Get response message
     *
     * @return string
     */
    public function getMessage(): string {
        return $this->message;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return APIResponse Self-reference
     */
    public function setPath(string $path): APIResponse {
        $this->path = $path;
        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Set HTTP status code
     *
     * @param int $status_code
     * @return APIResponse Self-reference
     */
    public function setStatusCode(int $status_code): APIResponse {
        $this->status_code = $status_code;
        return $this;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int {
        return $this->status_code;
    }

    /**
     * Set args
     *
     * @param array $args
     * @return APIResponse Self-reference
     */
    public function setArgs(array $args): APIResponse {
        $this->args = $args;
        return $this;
    }

    /**
     * Check if request was successful
     *
     * @return bool
     */
    public function isSuccess(): bool {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    /**
     * Render method
     *
     * @return string
     */
    public function render(): string {
        $response = [
            'success' => $this->isSuccess(),
            'message' => $this->message,
            'path' => $this->path,
            'args' => $this->args,
        ];
        if ($response['success']) {
            $response['data'] = $this->data;
        }
        return json_encode($response, $this->pretty ? JSON_PRETTY_PRINT : 0);
    }

}
