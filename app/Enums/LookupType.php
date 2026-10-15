<?php

namespace App\Enums;

enum LookupType: string
{
    case BloodType = 'blood-type';
    case BodyType = 'body-type';
    case Course = 'course';
    case EmploymentStatus = 'employment-status';
    case HealthIssue = 'health-issue';
    case IncomeBracket = 'income-bracket';
    case Nationality = 'nationality';
    case Position = 'position';
    case Profession = 'profession';
    case Religion = 'religion';
    case School = 'school';
    case Skill = 'skill';
    case Title = 'title';
    case ResidenceType = 'residence-type';
    case Company = 'company';
    case Relationship = 'relationship';
    case Sector = 'sector';
    case ClearanceType = 'clearance-type';
    case ClearancePurpose = 'clearance-purpose';
    case BusinessCategory = 'business-category';
    case BusinessNature = 'business-nature';
    case BusinessOwnershipType = 'business-ownership-type';
    case BlotterIncidentType = 'blotter-incident-type';
    case CertificatePurpose = 'certificate-purpose';
    case FormTemplateType = 'form-template-type';

    public function labelKey(): string
    {
        return "lookups.types.{$this->value}";
    }
}
