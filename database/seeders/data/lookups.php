<?php

use App\Enums\LookupType;

return [
    LookupType::BloodType->value => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
    LookupType::BodyType->value => ['Small', 'Medium', 'Large'],
    LookupType::Title->value => ['Mr.', 'Mrs.', 'Ms.'],
    LookupType::Nationality->value => ['Filipino', 'Korean', 'American'],
    LookupType::Religion->value => [
        'Roman Catholic', 'Christian', 'Iglesia ni Cristo', 'Born Again', 'Islam', 'Seventh-day Adventist',
        'Aglipayan (Iglesia Filipina Independiente)', 'Pentecostal', 'Protestant Christian', "Jehovah's Witness",
        'Bible Baptist', 'United Church of Christ in the Philippines', 'Assemblies of God', 'Evangelical',
    ],
    LookupType::HealthIssue->value => ['Hypertension', 'Diabetes', 'Physically fit', 'Kidney stone', 'Asthma', 'Orthopedic condition', 'Dialysis patient'],
    LookupType::Skill->value => [
        'Public speaking', 'Dressmaking', 'Computer literate', 'Typing', 'Cooking', 'Singing', 'Sewing', 'Welding',
        'Driving', 'Administrative collector', 'Administrative aide', 'Electrician', 'Flower arrangement',
        'Massage therapy', 'Reflexology', 'Dancing',
    ],
    LookupType::ResidenceType->value => ['Owned', 'Renting'],
    LookupType::IncomeBracket->value => [
        'Below 10,000', '10,000 to 19,999', '20,000 to 39,999', '40,000 to 69,999', '70,000 to 119,999', '120,000 and above',
    ],
    LookupType::Relationship->value => [
        'Father', 'Mother', 'Brother', 'Sister', 'Nephew', 'Niece', 'Son', 'Daughter', 'Live-in partner', 'Husband', 'Wife',
        'Grandson', 'Granddaughter', 'Spouse', 'Grandfather', 'Grandmother', 'Guardian',
    ],
    LookupType::EmploymentStatus->value => ['Permanent', 'Contractual', 'Casual', 'Job order', 'Self-employed', 'Part-time'],
    LookupType::Sector->value => [
        'Pregnant', 'Senior citizens organization', 'Lactating mother', 'Women', 'Out-of-school youth (OSY)', 'No toilet',
        'No running water', '4Ps beneficiary', 'Indigent', 'Farmer', 'Fisherfolk', 'Trisikad driver', 'Habal-habal driver',
        'Tricycle driver', 'Porter', 'Fish vendor', 'Market vendor', 'Malnourished',
    ],
    LookupType::ClearanceType->value => ['Barangay Clearance', 'Barangay Certification', 'Indigency', 'Residency'],
    LookupType::ClearancePurpose->value => [
        'Employment', 'BIR requirement', 'Bank requirement', 'DSWD requirement', 'GSIS requirement', 'SSS requirement',
        'PhilHealth requirement', 'NBI clearance', 'Police clearance', 'PSA requirement', 'Passport application',
        'Visa application', 'Postal ID', "Driver's license", 'TESDA requirement', '4Ps requirement', 'PWD membership',
        'Solo parent membership', 'Senior citizen membership', 'Scholarship', 'SPES', 'Medical assistance',
        'Burial assistance', 'Hospital billing assistance', 'Loan application', 'Co-maker or guarantor', 'Bail',
        'Probation', 'OJT or immersion', 'School requirement', 'House construction', 'Animal bite treatment',
        'Identification', 'Proof of residency', 'Any legal purpose',
    ],
    LookupType::BusinessCategory->value => [
        'Retail', 'Wholesale', 'Food and beverage', 'Personal services', 'Repair and maintenance', 'Transport',
        'Manufacturing', 'Agriculture', 'Construction', 'Rental and lodging',
    ],
    LookupType::BusinessNature->value => [
        'Sari-sari store', 'Eatery', 'Bakery', 'Market stall', 'Pharmacy', 'Hardware', 'Salon or barbershop',
        'Laundry', 'Vehicle repair', 'Appliance repair', 'Internet cafe', 'Water refilling station', 'Boarding house',
    ],
    LookupType::BusinessOwnershipType->value => ['Sole proprietorship', 'Partnership', 'Corporation', 'Cooperative'],
    LookupType::BlotterIncidentType->value => [
        'Physical injury', 'Threat', 'Theft', 'Trespass', 'Unjust vexation', 'Oral defamation', 'Damage to property',
        'Noise disturbance', 'Unpaid debt', 'Boundary dispute', 'Animal nuisance', 'Family dispute',
    ],
    LookupType::CertificatePurpose->value => [
        'Medical assistance', 'Burial assistance', 'Hospital bill assistance', 'Educational assistance', 'Scholarship',
        'Legal assistance', 'Financial assistance', 'Livelihood assistance', 'Any legal purpose',
    ],
    LookupType::FormTemplateType->value => [
        'Certificates and clearances', 'Katarungang Pambarangay', 'Barangay permits', 'Application forms', 'Resolutions',
    ],
];
