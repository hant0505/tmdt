<?php

declare(strict_types=1);

namespace Vendor\CheckoutAddress\Plugin\Checkout;

class LayoutProcessorPlugin
{
    /**
     * Customize shipping address form fields for Vietnam-focused checkout.
     *
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function afterProcess(
        \Magento\Checkout\Block\Checkout\LayoutProcessor $subject,
        array $jsLayout
    ): array {
        $fieldset = &$jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']
            ['shippingAddress']['children']['shipping-address-fieldset']['children'];

        if (!is_array($fieldset)) {
            return $jsLayout;
        }

        $this->setFieldLabel($fieldset, 'firstname', 'Tên');
        $this->setFieldLabel($fieldset, 'lastname', 'Họ');
        $this->setFieldLabel($fieldset, 'country_id', 'Quốc gia/Khu vực');
        $this->setFieldLabel($fieldset, 'telephone', 'Số điện thoại');

        // Lock shipping country to Vietnam and hide country selector.
        if (isset($fieldset['country_id']) && is_array($fieldset['country_id'])) {
            $fieldset['country_id']['value'] = 'VN';
            $fieldset['country_id']['default'] = 'VN';
            $fieldset['country_id']['visible'] = false;
            $fieldset['country_id']['validation']['required-entry'] = true;
            $fieldset['country_id']['sortOrder'] = 44;

            if (!isset($fieldset['country_id']['config']) || !is_array($fieldset['country_id']['config'])) {
                $fieldset['country_id']['config'] = [];
            }

            $fieldset['country_id']['config']['default'] = 'VN';
        }

        $this->setFieldVisibility($fieldset, 'company', false);
        $this->setFieldLabel($fieldset, 'region', 'Quận/Huyện');
        $this->setFieldLabel($fieldset, 'region_id', 'Quận/Huyện');
        $this->setFieldVisibility($fieldset, 'region', true);
        $this->setFieldVisibility($fieldset, 'region_id', false);

        if (isset($fieldset['region']) && is_array($fieldset['region'])) {
            $fieldset['region']['visible'] = true;
            $fieldset['region']['required'] = true;
            $fieldset['region']['validation']['required-entry'] = true;
            $fieldset['region']['sortOrder'] = 46;
        }

        if (isset($fieldset['city']) && is_array($fieldset['city'])) {
            $fieldset['city']['label'] = __('Tỉnh/Thành Phố');
            $fieldset['city']['visible'] = true;
            $fieldset['city']['required'] = true;
            $fieldset['city']['validation']['required-entry'] = true;
            $fieldset['city']['sortOrder'] = 45;
        }

        if (isset($fieldset['street']) && is_array($fieldset['street'])) {
            $fieldset['street']['label'] = __('Địa chỉ');
            $fieldset['street']['required'] = true;
            $fieldset['street']['sortOrder'] = 47;
            $fieldset['street']['size'] = 2;
            $fieldset['street']['additionalClasses'] = 'tekntek-street-2-lines';

            if (isset($fieldset['street']['children'][0]) && is_array($fieldset['street']['children'][0])) {
                $fieldset['street']['children'][0]['label'] = __('Phường/Xã');
                $fieldset['street']['children'][0]['placeholder'] = __('Phường/Xã');
                $fieldset['street']['children'][0]['validation']['required-entry'] = true;
                $fieldset['street']['children'][0]['sortOrder'] = 10;
            }

            if (isset($fieldset['street']['children'][1]) && is_array($fieldset['street']['children'][1])) {
                $fieldset['street']['children'][1]['visible'] = true;
                $fieldset['street']['children'][1]['label'] = __('Tên đường, Tòa nhà, Số nhà');
                $fieldset['street']['children'][1]['placeholder'] = __('Tên đường, Tòa nhà, Số nhà');
                $fieldset['street']['children'][1]['validation']['required-entry'] = true;
                $fieldset['street']['children'][1]['sortOrder'] = 20;
            }

            if (!isset($fieldset['street']['children'][2])) {
                if (isset($fieldset['street']['children'][1]) && is_array($fieldset['street']['children'][1])) {
                    $fieldset['street']['children'][2] = $fieldset['street']['children'][1];
                } elseif (isset($fieldset['street']['children'][0]) && is_array($fieldset['street']['children'][0])) {
                    $fieldset['street']['children'][2] = $fieldset['street']['children'][0];
                }
            }

            if (isset($fieldset['street']['children'][2]) && is_array($fieldset['street']['children'][2])) {
                $fieldset['street']['children'][2]['visible'] = false;
                $fieldset['street']['children'][2]['validation']['required-entry'] = false;
                $fieldset['street']['children'][2]['sortOrder'] = 30;
            }
        }

        if (!empty($_GET['tekntekCheckoutDebug']) && isset($fieldset['street']) && is_array($fieldset['street'])) {
            $logPath = defined('BP') ? BP . '/var/log/tekntek_checkout.log' : null;
            $payload = [
                'street' => $fieldset['street'],
                'timestamp' => date('c')
            ];

            if ($logPath) {
                @file_put_contents(
                    $logPath,
                    '[TeknTek Checkout Debug] ' . json_encode($payload) . PHP_EOL,
                    FILE_APPEND
                );
            }
        }

        if (isset($fieldset['postcode']) && is_array($fieldset['postcode'])) {
            $fieldset['postcode']['label'] = __('Mã bưu điện (tùy chọn)');
            $fieldset['postcode']['required'] = false;
            if (!isset($fieldset['postcode']['validation']) || !is_array($fieldset['postcode']['validation'])) {
                $fieldset['postcode']['validation'] = [];
            }
            $fieldset['postcode']['validation']['required-entry'] = false;
            if (!isset($fieldset['postcode']['config']) || !is_array($fieldset['postcode']['config'])) {
                $fieldset['postcode']['config'] = [];
            }
            $fieldset['postcode']['config']['required'] = false;
        }

        if (isset($fieldset['save_in_address_book']) && is_array($fieldset['save_in_address_book'])) {
            $fieldset['save_in_address_book']['visible'] = false;
            $fieldset['save_in_address_book']['value'] = 1;
            $fieldset['save_in_address_book']['default'] = 1;
        }

        return $jsLayout;
    }

    /**
     * @param array $fieldset
     * @param string $field
     * @param string $label
     */
    private function setFieldLabel(array &$fieldset, string $field, string $label): void
    {
        if (isset($fieldset[$field]) && is_array($fieldset[$field])) {
            $fieldset[$field]['label'] = __($label);
        }
    }

    /**
     * @param array $fieldset
     * @param string $field
     * @param bool $visible
     */
    private function setFieldVisibility(array &$fieldset, string $field, bool $visible): void
    {
        if (isset($fieldset[$field]) && is_array($fieldset[$field])) {
            $fieldset[$field]['visible'] = $visible;
            if ($visible === false) {
                $fieldset[$field]['validation']['required-entry'] = false;
            }
        }
    }
}
