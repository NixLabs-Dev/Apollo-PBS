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
 * This file is a delegate for module. Class does not extend any other class.
 *
 * All methods provided in this example are optional, but function names are
 * still reserved.
 */

namespace Box\Mod\Serviceproxmoxbackup;

use FOSSBilling\InformationException;

class Service
{
    protected $di;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function install()
    {
        $sql = '
        CREATE TABLE `service_proxmoxbackup` (
            `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `client_id` BIGINT(20),
            `config` TEXT,
            `created_at` DATETIME,
            `updated_at` DATETIME,
            PRIMARY KEY (`id`),
            KEY `client_id_idx` (`client_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

        $this->di['db']->exec($sql);
    }

    public function uninstall()
    {
        $this->di['db']->exec('DROP TABLE IF EXISTS `service_proxmoxbackup`');
    }

    public function create($order)
    {

        $logger = $this->di["logger"];

        $logger->info(json_encode($order));

        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $model = $this->di['db']->dispense('service_proxmoxbackup');
        $model->client_id = $order->client_id;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return $model;
    }


    /**
     * @return bool
     */
    public function action_activate(\Model_ClientOrder $order)
    {
        if (!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }

        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created', null, 7456);
        }

        $this->callOnAdapter($model, 'activate');

        return true;
    }

        /**
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);
        $this->callOnAdapter($model, 'renew');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'suspend');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'unsuspend');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'cancel');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'uncancel');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        try {
            $model = $this->_getOrderService($order);
        } catch (\Exception $e) {
            error_log($e);

            return true;
        }

        $this->callOnAdapter($model, 'delete');
        $this->di['db']->trash($model);

        return true;
    }


    // Handle Service Configuration

    public function getConfig($model): array
    {
        if (is_string($model->config) && json_validate($model->config)) {
            return json_decode($model->config, true);
        }

        return [];
    }

    public function updateConfig($orderId, $config)
    {
        if (!is_array($config)) {
            throw new \FOSSBilling\Exception('Config must be an array');
        }

        $model = $this->getServiceColocationByOrderId($orderId);
        $model->config = json_encode($config);
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Custom service updated #%s', $model->id);
    }


    // Get services from database
    public function getServiceColocationByOrderId($orderId)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        // if (!$s) {
        //     throw new \FOSSBilling\Exception('Order is not activated');
        // }

        return $s;
    }


    public function toApiArray($model): array
    {
        $data = $this->getConfig($model);
        $data['id'] = $model->id;
        $data['client_id'] = $model->client_id;
        $data['updated_at'] = $model->updated_at;
        $data['created_at'] = $model->created_at;

        return $data;
    }

    // Adapter Calls
    private function callOnAdapter($model, $method, $params = [])
    {
        $plugin = $model->plugin;
        if (empty($plugin)) {
            // error_log('Plugin is not used for this custom service');
            return null;
        }

        // check if plugin exists. If plugin does not exist, do not throw error. Simply add to log
        $file = sprintf('Plugin/%s/%s.php', $plugin, $plugin);
        if (!Environment::isTesting() && !file_exists(PATH_LIBRARY . DIRECTORY_SEPARATOR . $file)) {
            $e = new \FOSSBilling\Exception('Plugin class file :file was not found', [':file' => $file], 3124);
            if (DEBUG) {
                error_log($e->getMessage());
            }

            return null;
        }

        require_once $file;

        if (is_string($model->plugin_config) && json_validate($model->plugin_config)) {
            $config = json_decode($model->plugin_config, true);
        } else {
            $config = [];
        }

        $adapter = new $plugin($config);

        if (!method_exists($adapter, $method)) {
            throw new \FOSSBilling\Exception('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function _getOrderService(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }

        return $model;
    }
}
