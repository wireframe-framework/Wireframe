<?php

namespace Wireframe;

/**
 * Wireframe API Endpoints
 *
 * This class implements the Wireframe API default endpoints.
 *
 * @internal APIEndpoints is only intended for use within the Wireframe internals.
 *
 * @version 0.1.0
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
     * @throws \Wireframe\APIException if unknown component was requested (HTTP 404).
     * @throws \Wireframe\APIException if an error occurred while processing the component (HTTP 500).
     */
    public function components(array $path, array $args = []): array {
        if (empty($path)) {
            throw (new \Wireframe\APIException('Missing component name'))
                ->setResponseCode(400);
        }
        $component_name = $path[0] ?? null;
        try {
            $component = \Wireframe\Factory::component($component_name, $args);
            return [
                'json' => json_decode($component->renderJSON()),
                'rendered' => $component->render(),
            ];
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                throw (new \Wireframe\APIException(sprintf(
                    'Unknown component (%s)',
                    $component_name
                )))->setResponseCode(404);
            } else {
                throw (new \Wireframe\APIException(sprintf(
                    'Error while processing component (%s)',
                    $component_name
                )));
            }
        }
    }

    /**
     * Pages endpoint
     *
     * Note: this method is a temporary placeholder.
     *
     * @param array $path
     * @param array $args
     * @return array
     */
    public function pages(array $path, array $args = []): array {
        return [];
    }

    /**
     * Partials endpoint
     *
     * Note: this method is a temporary placeholder.
     *
     * @param array $path
     * @param array $args
     * @return array
     */
    public function partials(array $path, array $args = []): array {
        return [];
    }

}
