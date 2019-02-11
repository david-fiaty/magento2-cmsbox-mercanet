<?php
/**
 * Cmsbox.fr Magento 2 Payment module (https://www.cmsbox.fr)
 *
 * Copyright (c) 2017 Cmsbox.fr (https://www.cmsbox.fr)
 * Author: David Fiaty | contact@cmsbox.fr
 *
 * PHP version 7
 *
 * @category  Cmsbox
 * @package   Mercanet
 * @author    Cmsbox.fr <contact@cmsbox.fr> 
 * @copyright Cmsbox.fr all rights reserved.
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://www.cmsbox.fr
 */

namespace Cmsbox\Mercanet\Model\Adminhtml\Source;

class Environment implements \Magento\Framework\Option\ArrayInterface
{

    const ENVIRONMENT_PROD = 'prod';
    const ENVIRONMENT_TEST = 'test';
    const ENVIRONMENT_SIMU = 'simu';

    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ENVIRONMENT_SIMU,
                'label' => __('Simulation'),
            ],
            [
                'value' => self::ENVIRONMENT_TEST,
                'label' => __('Test'),
            ],
            [
                'value' => self::ENVIRONMENT_PROD,
                'label' => __('Production'),
            ],
        ];
    }

}
