<?php

namespace Wireframe;

/**
 * Wireframe API Endpoints
 *
 * This class implements the Wireframe API default endpoints.
 *
 * @internal This class is only intended for use within the Wireframe internals.
 *
 * @version 0.1.2
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class APIEndpoints {

    /**
     * Components endpoint
     *
     * Note that for the JSON output to return something, component must implement renderJSON() method.
     * While we *could* also pull data using getData(), this isn't necessarily expected behaviour, and
     * this way JSON output is not strictly tied to the "regular" render method.
     *
     * @param array $path
     * @param array $args
     * @return array
     *
     * @throws \Wireframe\APIException if no component name was specified (HTTP 400).
     * @throws \Wireframe\APIException if path has too many parts in it (HTTP 400).
     * @throws \Wireframe\APIException if unknown component was requested (HTTP 404).
     * @throws \Wireframe\APIException if an error occurred while processing the component (HTTP 500).
     */
    public function components(array $path, array $args = []): array {

        // bail out early if path is empty or has too many parts in it
        if (empty($path) || empty($path[0])) {
            throw (new \Wireframe\APIException('Missing component name'))
                ->setResponseCode(400);
        }
        if (isset($path[2])) {
            throw (new \Wireframe\APIException('Path has too many parts'))
                ->setResponseCode(400);
        }

        // get component name from path
        $component_name = $path[0];

        // set return format
        $return_format = $path[1] ?? null;
        if ($return_format !== null && !in_array($return_format, ['json', 'rendered'])) {
            $return_format = null;
        }

        try {
            $component = \Wireframe\Factory::component($component_name, $args);
            $out = [];
            if ($return_format === null || $return_format === 'json') {
                $out['json'] = json_decode($component->renderJSON());
            }
            if ($return_format === null || $return_format === 'rendered') {
                $out['rendered'] = $component->render();
            }
            return $out;
        } catch (\Throwable $e) {
            if ($e->getCode() === 404) {
                throw (new \Wireframe\APIException(sprintf(
                    'Unknown component (%s)',
                    $component_name
                )))->setResponseCode(404);
            }
            throw (new \Wireframe\APIException(sprintf(
                'Error while processing component (%s)%s',
                $component_name,
                $this->verboseErrors() ? ': ' . trim($e->getMessage()) : ''
            )));
        }
    }

    /**
     * Pages endpoint
     *
     * @param array $path
     * @param array $args
     * @return array
     *
     * @throws \Wireframe\APIException if no page ID was specified (HTTP 400).
     * @throws \Wireframe\APIException if path has too many parts in it (HTTP 400).
     * @throws \Wireframe\APIException if invalid ID was specified (HTTP 400).
     * @throws \Wireframe\APIException if non-existing or non-viewable page was requested (HTTP 404).
     * @throws \Wireframe\APIException if an error occurred while processing the page (HTTP 500).
     */
    public function pages(array $path, array $args = []): array {

        // bail out early if path is empty or has too many parts in it
        if (empty($path) || empty($path[0])) {
            throw (new \Wireframe\APIException('Missing page ID'))
                ->setResponseCode(400);
        }
        if (isset($path[2])) {
            throw (new \Wireframe\APIException('Path has too many parts'))
                ->setResponseCode(400);
        }

        // get page ID from path
        $page_id = (int) $path[0];
        if ($page_id < 1) {
            throw (new \Wireframe\APIException(sprintf(
                'Invalid page ID (%s)',
                $path[0]
            )))->setResponseCode(400);
        }

        // set return format
        $return_format = $path[1] ?? null;
        if ($return_format !== null && !in_array($return_format, ['json', 'rendered'])) {
            $return_format = null;
        }

        try {
            $page = \Wireframe\Factory::page($page_id, $args);
            if ($page instanceof \ProcessWire\NullPage || !$page->viewable()) {
                throw new \ProcessWire\Wire404Exception();
            }
            $page->wire('page', $page);
            $urlSegmentCount = \count($page->wire('input')->urlSegments());
            if ($urlSegmentCount) {
                for ($urlSegmentNum = 0; $urlSegmentNum < $urlSegmentCount; $urlSegmentNum++) {
                    // note: num is always 1 since removing the first segment resets keys for the rest of them.
                    $page->wire('input')->setUrlSegment(1, null);
                }
            }
            $out = [];
            if ($return_format === null || $return_format === 'json') {
                $controller = $page->getController();
                $out['json'] = $controller ? json_decode($controller->renderJSON()) : null;
            }
            if ($return_format === null || $return_format === 'rendered') {
                try {
                    ob_start();
                    $out['rendered'] = $page->render();
                } catch (\Throwable $e) {
                    throw $e;
                } finally {
                    ob_end_clean();
                }
            }
            return $out;
        } catch (\Throwable $e) {
            if ($e instanceof \ProcessWire\Wire404Exception) {
                throw (new \Wireframe\APIException(sprintf(
                    'Non-existing or non-viewable page (id=%s)',
                    $page_id
                )))->setResponseCode(404);
            }
            throw (new \Wireframe\APIException(sprintf(
                'Error while processing page (id=%s)%s',
                $page_id,
                $this->verboseErrors() ? ': ' . trim($e->getMessage()) : ''
            )));
        }
    }

    /**
     * Partials endpoint
     *
     * @param array $path
     * @param array $args
     * @return array
     *
     * @throws \Wireframe\APIException if no partial name was specified (HTTP 400).
     * @throws \Wireframe\APIException if unknown partial was requested (HTTP 404).
     * @throws \Wireframe\APIException if an error occurred while processing the partial (HTTP 500).
     */
    public function partials(array $path, array $args = []): array {

        // bail out early if path is empty or has too many parts in it
        if (empty($path) || empty($path[0])) {
            throw (new \Wireframe\APIException('Missing partial name'))
                ->setResponseCode(400);
        }

        // get partial name from path
        $partial_name = implode('/', $path);

        try {
            return [
                'rendered' => \Wireframe\Factory::partial($partial_name, $args),
            ];
        } catch (\Throwable $e) {
            if ($e->getCode() === 404) {
                throw (new \Wireframe\APIException(sprintf(
                    'Unknown partial (%s)',
                    $e->getMessage()
                )))->setResponseCode(404);
            }
            throw (new \Wireframe\APIException(sprintf(
                'Error while processing partial (%s)%s',
                $partial_name,
                $this->verboseErrors() ? ': ' . trim($e->getMessage()) : ''
            )));
        }
    }

    /**
     * Should we display verbose error/debug messages?
     *
     * @return bool
     */
    private function verboseErrors(): bool {
        return \ProcessWire\wire('config')->debug === true || \ProcessWire\wire('user')->isSuperuser();
    }

}
