<?php

declare(strict_types=1);

namespace Vendor\CheckoutAddress\Controller\Address;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;

class Save implements ActionInterface, HttpPostActionInterface
{
    private JsonFactory $resultJsonFactory;
    private CustomerSession $customerSession;
    private AddressRepositoryInterface $addressRepository;
    private AddressInterfaceFactory $addressFactory;
    private RegionInterfaceFactory $regionFactory;
    private RequestInterface $request;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerSession $customerSession,
        AddressRepositoryInterface $addressRepository,
        AddressInterfaceFactory $addressFactory,
        RegionInterfaceFactory $regionFactory
    ) {
        $this->request = $context->getRequest();
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->addressRepository = $addressRepository;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            if (!$this->customerSession->isLoggedIn()) {
                throw new LocalizedException(__('Please sign in before saving an address.'));
            }

            $customerId = (int)$this->customerSession->getCustomerId();
            $payload = $this->request->getParam('address', []);

            if (!is_array($payload)) {
                throw new LocalizedException(__('Invalid address data.'));
            }

            $addressId = (int)($this->request->getParam('address_id') ?: ($payload['id'] ?? 0));
            $address = $addressId ? $this->addressRepository->getById($addressId) : $this->addressFactory->create();

            if ($addressId && (int)$address->getCustomerId() !== $customerId) {
                throw new LocalizedException(__('This address does not belong to the current customer.'));
            }

            $address->setCustomerId($customerId);
            $this->applyAddressData($address, $payload);
            $savedAddress = $this->addressRepository->save($address);

            return $result->setData([
                'success' => true,
                'address_id' => (int)$savedAddress->getId()
            ]);
        } catch (\Throwable $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $exception->getMessage()
            ]);
        }
    }

    private function applyAddressData(AddressInterface $address, array $payload): void
    {
        $address->setFirstname($this->scalar($payload['firstname'] ?? ''));
        $address->setLastname($this->scalar($payload['lastname'] ?? ''));
        $address->setTelephone($this->scalar($payload['telephone'] ?? ''));
        $address->setStreet($this->street($payload['street'] ?? []));
        $address->setCity($this->scalar($payload['city'] ?? ''));
        $address->setPostcode($this->scalar($payload['postcode'] ?? ''));
        $address->setCountryId($this->scalar($payload['country_id'] ?? $payload['countryId'] ?? 'VN') ?: 'VN');
        $address->setCompany($this->scalar($payload['company'] ?? ''));
        $address->setMiddlename($this->scalar($payload['middlename'] ?? ''));
        $address->setPrefix($this->scalar($payload['prefix'] ?? ''));
        $address->setSuffix($this->scalar($payload['suffix'] ?? ''));
        $address->setVatId($this->scalar($payload['vat_id'] ?? $payload['vatId'] ?? ''));

        $regionText = $this->regionText($payload);
        $region = $this->regionFactory->create();
        $region->setRegion($regionText);
        $region->setRegionCode($regionText);
        $region->setRegionId(0);
        $address->setRegion($region);
        $address->setRegionId(null);
    }

    private function street($street): array
    {
        if (!is_array($street)) {
            $street = [$street];
        }

        $lines = [];
        foreach ($street as $line) {
            $line = $this->scalar($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines ?: [''];
    }

    private function regionText(array $payload): string
    {
        $region = $payload['region'] ?? '';

        if (is_array($region)) {
            $region = $region['region'] ?? $region['label'] ?? $region['regionCode'] ?? '';
        }

        return $this->scalar($region);
    }

    private function scalar($value): string
    {
        if (is_array($value)) {
            foreach (['value', 'label', 'text', 'region', 'name'] as $key) {
                if (isset($value[$key])) {
                    return $this->scalar($value[$key]);
                }
            }

            return '';
        }

        if ($value === null) {
            return '';
        }

        return trim((string)$value);
    }
}
