<?php

declare(strict_types=1);

namespace BrewCraft\BusinessAccount\Model;

use BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount as BusinessAccountResource;
use Magento\Framework\Model\AbstractModel;

class BusinessAccount extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $_eventPrefix = 'brewcraft_business_account';

    protected $_eventObject = 'business_account';

    protected function _construct(): void
    {
        $this->_init(BusinessAccountResource::class);
    }

    public function getEntityId(): ?int
    {
        $entityId = $this->getData('entity_id');

        return $entityId !== null ? (int)$entityId : null;
    }

    public function getCustomerId(): ?int
    {
        $customerId = $this->getData('customer_id');

        return $customerId !== null ? (int)$customerId : null;
    }

    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getCompanyName(): string
    {
        return (string)$this->getData('company_name');
    }

    public function setCompanyName(string $companyName): self
    {
        return $this->setData('company_name', $companyName);
    }

    public function getRegistrationNumber(): string
    {
        return (string)$this->getData('registration_number');
    }

    public function setRegistrationNumber(string $registrationNumber): self
    {
        return $this->setData(
            'registration_number',
            $registrationNumber
        );
    }

    public function getTaxNumber(): ?string
    {
        $taxNumber = $this->getData('tax_number');

        return $taxNumber !== null ? (string)$taxNumber : null;
    }

    public function setTaxNumber(?string $taxNumber): self
    {
        return $this->setData('tax_number', $taxNumber);
    }

    public function getCompanyType(): ?string
    {
        $companyType = $this->getData('company_type');

        return $companyType !== null ? (string)$companyType : null;
    }

    public function setCompanyType(?string $companyType): self
    {
        return $this->setData('company_type', $companyType);
    }

    public function getBusinessYears(): ?int
    {
        $businessYears = $this->getData('business_years');

        return $businessYears !== null
            ? (int)$businessYears
            : null;
    }

    public function setBusinessYears(?int $businessYears): self
    {
        return $this->setData('business_years', $businessYears);
    }

    public function getContactName(): string
    {
        return (string)$this->getData('contact_name');
    }

    public function setContactName(string $contactName): self
    {
        return $this->setData('contact_name', $contactName);
    }

    public function getContactEmail(): string
    {
        return (string)$this->getData('contact_email');
    }

    public function setContactEmail(string $contactEmail): self
    {
        return $this->setData('contact_email', $contactEmail);
    }

    public function getContactPhone(): string
    {
        return (string)$this->getData('contact_phone');
    }

    public function setContactPhone(string $contactPhone): self
    {
        return $this->setData('contact_phone', $contactPhone);
    }

    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    public function getAdminComment(): ?string
    {
        $comment = $this->getData('admin_comment');

        return $comment !== null ? (string)$comment : null;
    }

    public function setAdminComment(?string $comment): self
    {
        return $this->setData('admin_comment', $comment);
    }

    public function getApprovedAt(): ?string
    {
        $approvedAt = $this->getData('approved_at');

        return $approvedAt !== null ? (string)$approvedAt : null;
    }

    public function setApprovedAt(?string $approvedAt): self
    {
        return $this->setData('approved_at', $approvedAt);
    }

    public function isPending(): bool
    {
        return $this->getStatus() === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->getStatus() === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->getStatus() === self::STATUS_REJECTED;
    }

    public static function getAllowedStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED
        ];
    }
}
