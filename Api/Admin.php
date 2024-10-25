<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Example module Admin API.
 *
 * API can be access only by admins
 */

namespace Box\Mod\Serviceproxmoxbackup\Api;

class Admin extends \Api_Abstract
{


        /**
     * Update custom service configuration.
     *
     * @return bool
     */
    public function update($data)
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }

        if (isset($data['config']) && is_array($data['config'])) {
            $this->getService()->updateConfig($data['order_id'], $data['config']);
        }

        return true;
    }

    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin.
     *
     * @throws \FOSSBilling\Exception
     */
    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \FOSSBilling\Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];

        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $model = $this->getService()->getServiceCustomByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }
}
