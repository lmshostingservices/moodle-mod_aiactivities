<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Industry and sector vocabulary used by the scenario generator.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiactivities\local;

/**
 * Provides the industry / sector vocabulary offered in the scenario options.
 */
class taxonomy {
    /**
     * The vocabulary, as a language string key and the canonical English value
     * for each entry. The value is what the generation service is sent, and is
     * deliberately independent of the language pack so that translating or
     * customising a label cannot change what the service receives.
     *
     * @var array[]
     */
    private const KEYS = [
        [
            'industry' => 'industry_agedcare',
            'value' => 'Aged Care',
            'sectors' => [
                ['key' => 'sector_agedca_residentialagedcare', 'value' => 'Residential Aged Care'],
                ['key' => 'sector_agedca_homecareservices', 'value' => 'Home Care Services'],
                ['key' => 'sector_agedca_dementiacare', 'value' => 'Dementia Care'],
                ['key' => 'sector_agedca_palliativecare', 'value' => 'Palliative Care'],
                ['key' => 'sector_agedca_communityagedcare', 'value' => 'Community Aged Care'],
                ['key' => 'sector_agedca_retirementliving', 'value' => 'Retirement Living'],
                ['key' => 'sector_agedca_respitecare', 'value' => 'Respite Care'],
                ['key' => 'sector_agedca_alliedhealthinagedcare', 'value' => 'Allied Health in Aged Care'],
            ],
        ],
        [
            'industry' => 'industry_agriculture',
            'value' => 'Agriculture',
            'sectors' => [
                ['key' => 'sector_agricu_croppingandgrain', 'value' => 'Cropping & Grain'],
                ['key' => 'sector_agricu_livestockandcattle', 'value' => 'Livestock & Cattle'],
                ['key' => 'sector_agricu_dairyfarming', 'value' => 'Dairy Farming'],
                ['key' => 'sector_agricu_horticulture', 'value' => 'Horticulture'],
                ['key' => 'sector_agricu_viticultureandwine', 'value' => 'Viticulture & Wine'],
                ['key' => 'sector_agricu_aquaculture', 'value' => 'Aquaculture'],
                ['key' => 'sector_agricu_poultry', 'value' => 'Poultry'],
                ['key' => 'sector_agricu_shearingandwool', 'value' => 'Shearing & Wool'],
                ['key' => 'sector_agricu_agriculturalcontracting', 'value' => 'Agricultural Contracting'],
                ['key' => 'sector_agricu_irrigationandwatermanagement', 'value' => 'Irrigation & Water Management'],
            ],
        ],
        [
            'industry' => 'industry_automotive',
            'value' => 'Automotive',
            'sectors' => [
                ['key' => 'sector_automo_lightvehiclemechanical', 'value' => 'Light Vehicle Mechanical'],
                ['key' => 'sector_automo_heavyvehiclemechanical', 'value' => 'Heavy Vehicle Mechanical'],
                ['key' => 'sector_automo_autoelectrical', 'value' => 'Auto Electrical'],
                ['key' => 'sector_automo_panelbeatingandspraypainting', 'value' => 'Panel Beating & Spray Painting'],
                ['key' => 'sector_automo_motorcycletechnician', 'value' => 'Motorcycle Technician'],
                ['key' => 'sector_automo_marinemechanical', 'value' => 'Marine Mechanical'],
                ['key' => 'sector_automo_automotivepartsandaccessories', 'value' => 'Automotive Parts & Accessories'],
                ['key' => 'sector_automo_vehiclesales', 'value' => 'Vehicle Sales'],
                ['key' => 'sector_automo_tyrefitting', 'value' => 'Tyre Fitting'],
            ],
        ],
        [
            'industry' => 'industry_aviation',
            'value' => 'Aviation',
            'sectors' => [
                ['key' => 'sector_aviati_commercialaviation', 'value' => 'Commercial Aviation'],
                ['key' => 'sector_aviati_generalaviation', 'value' => 'General Aviation'],
                ['key' => 'sector_aviati_aircraftmaintenance', 'value' => 'Aircraft Maintenance'],
                ['key' => 'sector_aviati_groundoperations', 'value' => 'Ground Operations'],
                ['key' => 'sector_aviati_airtrafficcontrol', 'value' => 'Air Traffic Control'],
                ['key' => 'sector_aviati_cabincrew', 'value' => 'Cabin Crew'],
                ['key' => 'sector_aviati_aviationsecurity', 'value' => 'Aviation Security'],
                ['key' => 'sector_aviati_helicopteroperations', 'value' => 'Helicopter Operations'],
            ],
        ],
        [
            'industry' => 'industry_buildingandconstruction',
            'value' => 'Building & Construction',
            'sectors' => [
                ['key' => 'sector_buildi_residentialconstruction', 'value' => 'Residential Construction'],
                ['key' => 'sector_buildi_commercialconstruction', 'value' => 'Commercial Construction'],
                ['key' => 'sector_buildi_civilconstruction', 'value' => 'Civil Construction'],
                ['key' => 'sector_buildi_miningconstruction', 'value' => 'Mining Construction'],
                ['key' => 'sector_buildi_industrialconstruction', 'value' => 'Industrial Construction'],
                ['key' => 'sector_buildi_highriseconstruction', 'value' => 'High-Rise Construction'],
                ['key' => 'sector_buildi_renovationandrefurbishment', 'value' => 'Renovation & Refurbishment'],
                ['key' => 'sector_buildi_demolition', 'value' => 'Demolition'],
                ['key' => 'sector_buildi_scaffolding', 'value' => 'Scaffolding'],
                ['key' => 'sector_buildi_formwork', 'value' => 'Formwork'],
                ['key' => 'sector_buildi_concreting', 'value' => 'Concreting'],
                ['key' => 'sector_buildi_steelfixing', 'value' => 'Steel Fixing'],
                ['key' => 'sector_buildi_carpentry', 'value' => 'Carpentry'],
                ['key' => 'sector_buildi_bricklaying', 'value' => 'Bricklaying'],
                ['key' => 'sector_buildi_tiling', 'value' => 'Tiling'],
                ['key' => 'sector_buildi_paintinganddecorating', 'value' => 'Painting & Decorating'],
                ['key' => 'sector_buildi_plastering', 'value' => 'Plastering'],
                ['key' => 'sector_buildi_roofing', 'value' => 'Roofing'],
                ['key' => 'sector_buildi_glazing', 'value' => 'Glazing'],
                ['key' => 'sector_buildi_waterproofing', 'value' => 'Waterproofing'],
            ],
        ],
        [
            'industry' => 'industry_businessservices',
            'value' => 'Business Services',
            'sectors' => [
                ['key' => 'sector_busine_accountingandbookkeeping', 'value' => 'Accounting & Bookkeeping'],
                ['key' => 'sector_busine_humanresources', 'value' => 'Human Resources'],
                ['key' => 'sector_busine_marketingandadvertising', 'value' => 'Marketing & Advertising'],
                ['key' => 'sector_busine_legalservices', 'value' => 'Legal Services'],
                ['key' => 'sector_busine_consulting', 'value' => 'Consulting'],
                ['key' => 'sector_busine_recruitment', 'value' => 'Recruitment'],
                ['key' => 'sector_busine_traininganddevelopment', 'value' => 'Training & Development'],
                ['key' => 'sector_busine_propertymanagement', 'value' => 'Property Management'],
                ['key' => 'sector_busine_cleaningservices', 'value' => 'Cleaning Services'],
                ['key' => 'sector_busine_securityservices', 'value' => 'Security Services'],
            ],
        ],
        [
            'industry' => 'industry_childcare',
            'value' => 'Childcare',
            'sectors' => [
                ['key' => 'sector_childc_longdaycare', 'value' => 'Long Day Care'],
                ['key' => 'sector_childc_familydaycare', 'value' => 'Family Day Care'],
                ['key' => 'sector_childc_outsideschoolhourscare', 'value' => 'Outside School Hours Care'],
                ['key' => 'sector_childc_kindergartenpreschool', 'value' => 'Kindergarten/Preschool'],
                ['key' => 'sector_childc_occasionalcare', 'value' => 'Occasional Care'],
                ['key' => 'sector_childc_inhomecare', 'value' => 'In-Home Care'],
                ['key' => 'sector_childc_specialneedssupport', 'value' => 'Special Needs Support'],
                ['key' => 'sector_childc_earlyintervention', 'value' => 'Early Intervention'],
            ],
        ],
        [
            'industry' => 'industry_communityservices',
            'value' => 'Community Services',
            'sectors' => [
                ['key' => 'sector_commun_disabilitysupport', 'value' => 'Disability Support'],
                ['key' => 'sector_commun_mentalhealthsupport', 'value' => 'Mental Health Support'],
                ['key' => 'sector_commun_youthwork', 'value' => 'Youth Work'],
                ['key' => 'sector_commun_familyservices', 'value' => 'Family Services'],
                ['key' => 'sector_commun_homelessnessservices', 'value' => 'Homelessness Services'],
                ['key' => 'sector_commun_drugandalcoholservices', 'value' => 'Drug & Alcohol Services'],
                [
                    'key' => 'sector_commun_aboriginalandtorresstraitislanderservices',
                    'value' => 'Aboriginal & Torres Strait Islander Services',
                ],
                ['key' => 'sector_commun_refugeeandmigrantservices', 'value' => 'Refugee & Migrant Services'],
                ['key' => 'sector_commun_domesticviolencesupport', 'value' => 'Domestic Violence Support'],
                ['key' => 'sector_commun_casemanagement', 'value' => 'Case Management'],
            ],
        ],
        [
            'industry' => 'industry_education',
            'value' => 'Education',
            'sectors' => [
                ['key' => 'sector_educat_primaryeducation', 'value' => 'Primary Education'],
                ['key' => 'sector_educat_secondaryeducation', 'value' => 'Secondary Education'],
                ['key' => 'sector_educat_vocationaleducationvet', 'value' => 'Vocational Education (VET)'],
                ['key' => 'sector_educat_highereducationuniversity', 'value' => 'Higher Education/University'],
                ['key' => 'sector_educat_tafe', 'value' => 'TAFE'],
                ['key' => 'sector_educat_adulteducation', 'value' => 'Adult Education'],
                ['key' => 'sector_educat_specialeducation', 'value' => 'Special Education'],
                ['key' => 'sector_educat_earlychildhoodeducation', 'value' => 'Early Childhood Education'],
                ['key' => 'sector_educat_onlinedistanceeducation', 'value' => 'Online/Distance Education'],
                ['key' => 'sector_educat_educationsupport', 'value' => 'Education Support'],
                ['key' => 'sector_educat_trainingadministration', 'value' => 'Training Administration'],
                ['key' => 'sector_educat_schooladministration', 'value' => 'School Administration'],
                ['key' => 'sector_educat_privatetrainingproviderrto', 'value' => 'Private Training Provider (RTO)'],
            ],
        ],
        [
            'industry' => 'industry_electrical',
            'value' => 'Electrical',
            'sectors' => [
                ['key' => 'sector_electr_domesticelectrical', 'value' => 'Domestic Electrical'],
                ['key' => 'sector_electr_commercialelectrical', 'value' => 'Commercial Electrical'],
                ['key' => 'sector_electr_industrialelectrical', 'value' => 'Industrial Electrical'],
                ['key' => 'sector_electr_instrumentation', 'value' => 'Instrumentation'],
                ['key' => 'sector_electr_refrigerationandairconditioning', 'value' => 'Refrigeration & Air Conditioning'],
                ['key' => 'sector_electr_solarinstallation', 'value' => 'Solar Installation'],
                ['key' => 'sector_electr_dataandcommunications', 'value' => 'Data & Communications'],
                ['key' => 'sector_electr_fireprotectionsystems', 'value' => 'Fire Protection Systems'],
                ['key' => 'sector_electr_liftinstallation', 'value' => 'Lift Installation'],
            ],
        ],
        [
            'industry' => 'industry_engineering',
            'value' => 'Engineering',
            'sectors' => [
                ['key' => 'sector_engine_mechanicalengineering', 'value' => 'Mechanical Engineering'],
                ['key' => 'sector_engine_civilengineering', 'value' => 'Civil Engineering'],
                ['key' => 'sector_engine_structuralengineering', 'value' => 'Structural Engineering'],
                ['key' => 'sector_engine_electricalengineering', 'value' => 'Electrical Engineering'],
                ['key' => 'sector_engine_chemicalengineering', 'value' => 'Chemical Engineering'],
                ['key' => 'sector_engine_miningengineering', 'value' => 'Mining Engineering'],
                ['key' => 'sector_engine_environmentalengineering', 'value' => 'Environmental Engineering'],
                ['key' => 'sector_engine_projectengineering', 'value' => 'Project Engineering'],
                ['key' => 'sector_engine_maintenanceengineering', 'value' => 'Maintenance Engineering'],
            ],
        ],
        [
            'industry' => 'industry_finance',
            'value' => 'Finance',
            'sectors' => [
                ['key' => 'sector_financ_banking', 'value' => 'Banking'],
                ['key' => 'sector_financ_insurance', 'value' => 'Insurance'],
                ['key' => 'sector_financ_financialplanning', 'value' => 'Financial Planning'],
                ['key' => 'sector_financ_mortgagebroking', 'value' => 'Mortgage Broking'],
                ['key' => 'sector_financ_creditandlending', 'value' => 'Credit & Lending'],
                ['key' => 'sector_financ_superannuation', 'value' => 'Superannuation'],
                ['key' => 'sector_financ_investmentmanagement', 'value' => 'Investment Management'],
                ['key' => 'sector_financ_payroll', 'value' => 'Payroll'],
                ['key' => 'sector_financ_accountspayablereceivable', 'value' => 'Accounts Payable/Receivable'],
                ['key' => 'sector_financ_auditing', 'value' => 'Auditing'],
            ],
        ],
        [
            'industry' => 'industry_foodprocessing',
            'value' => 'Food Processing',
            'sectors' => [
                ['key' => 'sector_foodpr_meatprocessing', 'value' => 'Meat Processing'],
                ['key' => 'sector_foodpr_seafoodprocessing', 'value' => 'Seafood Processing'],
                ['key' => 'sector_foodpr_dairyprocessing', 'value' => 'Dairy Processing'],
                ['key' => 'sector_foodpr_bakery', 'value' => 'Bakery'],
                ['key' => 'sector_foodpr_beveragemanufacturing', 'value' => 'Beverage Manufacturing'],
                ['key' => 'sector_foodpr_confectionery', 'value' => 'Confectionery'],
                ['key' => 'sector_foodpr_fruitandvegetableprocessing', 'value' => 'Fruit & Vegetable Processing'],
                ['key' => 'sector_foodpr_readymealsandconveniencefoods', 'value' => 'Ready Meals & Convenience Foods'],
                ['key' => 'sector_foodpr_qualityassurance', 'value' => 'Quality Assurance'],
                ['key' => 'sector_foodpr_foodsafety', 'value' => 'Food Safety'],
            ],
        ],
        [
            'industry' => 'industry_government',
            'value' => 'Government',
            'sectors' => [
                ['key' => 'sector_govern_localgovernment', 'value' => 'Local Government'],
                ['key' => 'sector_govern_stategovernment', 'value' => 'State Government'],
                ['key' => 'sector_govern_federalgovernment', 'value' => 'Federal Government'],
                ['key' => 'sector_govern_emergencyservices', 'value' => 'Emergency Services'],
                ['key' => 'sector_govern_regulatoryandcompliance', 'value' => 'Regulatory & Compliance'],
                ['key' => 'sector_govern_policyandplanning', 'value' => 'Policy & Planning'],
                ['key' => 'sector_govern_customerservice', 'value' => 'Customer Service'],
                ['key' => 'sector_govern_parksandrecreation', 'value' => 'Parks & Recreation'],
                ['key' => 'sector_govern_infrastructure', 'value' => 'Infrastructure'],
                ['key' => 'sector_govern_communityengagement', 'value' => 'Community Engagement'],
            ],
        ],
        [
            'industry' => 'industry_healthcare',
            'value' => 'Healthcare',
            'sectors' => [
                ['key' => 'sector_health_acutecarehospital', 'value' => 'Acute Care/Hospital'],
                ['key' => 'sector_health_primarycaregp', 'value' => 'Primary Care/GP'],
                ['key' => 'sector_health_alliedhealth', 'value' => 'Allied Health'],
                ['key' => 'sector_health_mentalhealth', 'value' => 'Mental Health'],
                ['key' => 'sector_health_communityhealth', 'value' => 'Community Health'],
                ['key' => 'sector_health_dental', 'value' => 'Dental'],
                ['key' => 'sector_health_pharmacy', 'value' => 'Pharmacy'],
                ['key' => 'sector_health_pathology', 'value' => 'Pathology'],
                ['key' => 'sector_health_radiology', 'value' => 'Radiology'],
                ['key' => 'sector_health_emergencyservices', 'value' => 'Emergency Services'],
                ['key' => 'sector_health_surgical', 'value' => 'Surgical'],
                ['key' => 'sector_health_rehabilitation', 'value' => 'Rehabilitation'],
                ['key' => 'sector_health_infectioncontrol', 'value' => 'Infection Control'],
                ['key' => 'sector_health_agedcarenursing', 'value' => 'Aged Care Nursing'],
                ['key' => 'sector_health_midwifery', 'value' => 'Midwifery'],
                ['key' => 'sector_health_disabilityhealth', 'value' => 'Disability Health'],
                ['key' => 'sector_health_aboriginalhealth', 'value' => 'Aboriginal Health'],
            ],
        ],
        [
            'industry' => 'industry_hospitality',
            'value' => 'Hospitality',
            'sectors' => [
                ['key' => 'sector_hospit_hotelsandaccommodation', 'value' => 'Hotels & Accommodation'],
                ['key' => 'sector_hospit_restaurantsandcafes', 'value' => 'Restaurants & Cafes'],
                ['key' => 'sector_hospit_barsandpubs', 'value' => 'Bars & Pubs'],
                ['key' => 'sector_hospit_catering', 'value' => 'Catering'],
                ['key' => 'sector_hospit_eventsandfunctions', 'value' => 'Events & Functions'],
                ['key' => 'sector_hospit_fastfoodandquickservice', 'value' => 'Fast Food & Quick Service'],
                ['key' => 'sector_hospit_clubsandgaming', 'value' => 'Clubs & Gaming'],
                ['key' => 'sector_hospit_commercialcookery', 'value' => 'Commercial Cookery'],
                ['key' => 'sector_hospit_patisserie', 'value' => 'Patisserie'],
                ['key' => 'sector_hospit_frontoffice', 'value' => 'Front Office'],
                ['key' => 'sector_hospit_housekeeping', 'value' => 'Housekeeping'],
            ],
        ],
        [
            'industry' => 'industry_informationtechnology',
            'value' => 'Information Technology',
            'sectors' => [
                ['key' => 'sector_inform_softwaredevelopment', 'value' => 'Software Development'],
                ['key' => 'sector_inform_networkadministration', 'value' => 'Network Administration'],
                ['key' => 'sector_inform_cybersecurity', 'value' => 'Cybersecurity'],
                ['key' => 'sector_inform_cloudcomputing', 'value' => 'Cloud Computing'],
                ['key' => 'sector_inform_databaseadministration', 'value' => 'Database Administration'],
                ['key' => 'sector_inform_itsupporthelpdesk', 'value' => 'IT Support/Help Desk'],
                ['key' => 'sector_inform_webdevelopment', 'value' => 'Web Development'],
                ['key' => 'sector_inform_dataanalytics', 'value' => 'Data Analytics'],
                ['key' => 'sector_inform_systemsadministration', 'value' => 'Systems Administration'],
                ['key' => 'sector_inform_itprojectmanagement', 'value' => 'IT Project Management'],
            ],
        ],
        [
            'industry' => 'industry_logistics',
            'value' => 'Logistics',
            'sectors' => [
                ['key' => 'sector_logist_supplychainmanagement', 'value' => 'Supply Chain Management'],
                ['key' => 'sector_logist_freightforwarding', 'value' => 'Freight Forwarding'],
                ['key' => 'sector_logist_customsandborder', 'value' => 'Customs & Border'],
                ['key' => 'sector_logist_inventorymanagement', 'value' => 'Inventory Management'],
                ['key' => 'sector_logist_distribution', 'value' => 'Distribution'],
                ['key' => 'sector_logist_thirdpartylogistics3pl', 'value' => 'Third-Party Logistics (3PL)'],
                ['key' => 'sector_logist_lastmiledelivery', 'value' => 'Last Mile Delivery'],
                ['key' => 'sector_logist_coldchainlogistics', 'value' => 'Cold Chain Logistics'],
                ['key' => 'sector_logist_dangerousgoods', 'value' => 'Dangerous Goods'],
            ],
        ],
        [
            'industry' => 'industry_manufacturing',
            'value' => 'Manufacturing',
            'sectors' => [
                ['key' => 'sector_manufa_foodandbeveragemanufacturing', 'value' => 'Food & Beverage Manufacturing'],
                ['key' => 'sector_manufa_pharmaceuticalmanufacturing', 'value' => 'Pharmaceutical Manufacturing'],
                ['key' => 'sector_manufa_chemicalmanufacturing', 'value' => 'Chemical Manufacturing'],
                ['key' => 'sector_manufa_metalfabrication', 'value' => 'Metal Fabrication'],
                ['key' => 'sector_manufa_plasticsandrubber', 'value' => 'Plastics & Rubber'],
                ['key' => 'sector_manufa_textiles', 'value' => 'Textiles'],
                ['key' => 'sector_manufa_furnituremanufacturing', 'value' => 'Furniture Manufacturing'],
                ['key' => 'sector_manufa_electronicsmanufacturing', 'value' => 'Electronics Manufacturing'],
                ['key' => 'sector_manufa_printing', 'value' => 'Printing'],
                ['key' => 'sector_manufa_packaging', 'value' => 'Packaging'],
                ['key' => 'sector_manufa_processmanufacturing', 'value' => 'Process Manufacturing'],
            ],
        ],
        [
            'industry' => 'industry_mining',
            'value' => 'Mining',
            'sectors' => [
                ['key' => 'sector_mining_opencutmining', 'value' => 'Open Cut Mining'],
                ['key' => 'sector_mining_undergroundmining', 'value' => 'Underground Mining'],
                ['key' => 'sector_mining_coalmining', 'value' => 'Coal Mining'],
                ['key' => 'sector_mining_ironore', 'value' => 'Iron Ore'],
                ['key' => 'sector_mining_goldmining', 'value' => 'Gold Mining'],
                ['key' => 'sector_mining_mineralprocessing', 'value' => 'Mineral Processing'],
                ['key' => 'sector_mining_exploration', 'value' => 'Exploration'],
                ['key' => 'sector_mining_drilling', 'value' => 'Drilling'],
                ['key' => 'sector_mining_minesiteservices', 'value' => 'Mine Site Services'],
                ['key' => 'sector_mining_tailingsmanagement', 'value' => 'Tailings Management'],
                ['key' => 'sector_mining_minerehabilitation', 'value' => 'Mine Rehabilitation'],
            ],
        ],
        [
            'industry' => 'industry_plumbing',
            'value' => 'Plumbing',
            'sectors' => [
                ['key' => 'sector_plumbi_domesticplumbing', 'value' => 'Domestic Plumbing'],
                ['key' => 'sector_plumbi_commercialplumbing', 'value' => 'Commercial Plumbing'],
                ['key' => 'sector_plumbi_industrialplumbing', 'value' => 'Industrial Plumbing'],
                ['key' => 'sector_plumbi_gasfitting', 'value' => 'Gas Fitting'],
                ['key' => 'sector_plumbi_roofinganddrainage', 'value' => 'Roofing & Drainage'],
                ['key' => 'sector_plumbi_fireprotectionplumbing', 'value' => 'Fire Protection Plumbing'],
                ['key' => 'sector_plumbi_irrigation', 'value' => 'Irrigation'],
                ['key' => 'sector_plumbi_watertreatment', 'value' => 'Water Treatment'],
                ['key' => 'sector_plumbi_mechanicalservices', 'value' => 'Mechanical Services'],
            ],
        ],
        [
            'industry' => 'industry_retail',
            'value' => 'Retail',
            'sectors' => [
                ['key' => 'sector_retail_supermarketsandgrocery', 'value' => 'Supermarkets & Grocery'],
                ['key' => 'sector_retail_fashionandapparel', 'value' => 'Fashion & Apparel'],
                ['key' => 'sector_retail_electronicsandtechnology', 'value' => 'Electronics & Technology'],
                ['key' => 'sector_retail_hardwareandbuilding', 'value' => 'Hardware & Building'],
                ['key' => 'sector_retail_pharmacyretail', 'value' => 'Pharmacy Retail'],
                ['key' => 'sector_retail_furnitureandhomewares', 'value' => 'Furniture & Homewares'],
                ['key' => 'sector_retail_automotiveretail', 'value' => 'Automotive Retail'],
                ['key' => 'sector_retail_sportinggoods', 'value' => 'Sporting Goods'],
                ['key' => 'sector_retail_onlineecommerce', 'value' => 'Online/E-commerce'],
                ['key' => 'sector_retail_luxuryretail', 'value' => 'Luxury Retail'],
            ],
        ],
        [
            'industry' => 'industry_security',
            'value' => 'Security',
            'sectors' => [
                ['key' => 'sector_securi_staticsecurity', 'value' => 'Static Security'],
                ['key' => 'sector_securi_mobilepatrol', 'value' => 'Mobile Patrol'],
                ['key' => 'sector_securi_eventsecurity', 'value' => 'Event Security'],
                ['key' => 'sector_securi_closeprotection', 'value' => 'Close Protection'],
                ['key' => 'sector_securi_lossprevention', 'value' => 'Loss Prevention'],
                ['key' => 'sector_securi_corporatesecurity', 'value' => 'Corporate Security'],
                ['key' => 'sector_securi_cashintransit', 'value' => 'Cash in Transit'],
                ['key' => 'sector_securi_cctvandmonitoring', 'value' => 'CCTV & Monitoring'],
                ['key' => 'sector_securi_accesscontrol', 'value' => 'Access Control'],
                ['key' => 'sector_securi_cybersecurityoperations', 'value' => 'Cybersecurity Operations'],
            ],
        ],
        [
            'industry' => 'industry_sportandrecreation',
            'value' => 'Sport & Recreation',
            'sectors' => [
                ['key' => 'sector_sporta_fitnessandpersonaltraining', 'value' => 'Fitness & Personal Training'],
                ['key' => 'sector_sporta_aquatics', 'value' => 'Aquatics'],
                ['key' => 'sector_sporta_outdoorrecreation', 'value' => 'Outdoor Recreation'],
                ['key' => 'sector_sporta_sportscoaching', 'value' => 'Sports Coaching'],
                ['key' => 'sector_sporta_sportsadministration', 'value' => 'Sports Administration'],
                ['key' => 'sector_sporta_communityrecreation', 'value' => 'Community Recreation'],
                ['key' => 'sector_sporta_eventmanagement', 'value' => 'Event Management'],
                ['key' => 'sector_sporta_golfandturfmanagement', 'value' => 'Golf & Turf Management'],
                ['key' => 'sector_sporta_sportsmedicinesupport', 'value' => 'Sports Medicine Support'],
            ],
        ],
        [
            'industry' => 'industry_tourism',
            'value' => 'Tourism',
            'sectors' => [
                ['key' => 'sector_touris_travelagencies', 'value' => 'Travel Agencies'],
                ['key' => 'sector_touris_touroperations', 'value' => 'Tour Operations'],
                ['key' => 'sector_touris_attractionsandthemeparks', 'value' => 'Attractions & Theme Parks'],
                ['key' => 'sector_touris_ecotourism', 'value' => 'Eco-Tourism'],
                ['key' => 'sector_touris_adventuretourism', 'value' => 'Adventure Tourism'],
                ['key' => 'sector_touris_culturaltourism', 'value' => 'Cultural Tourism'],
                ['key' => 'sector_touris_cruiseoperations', 'value' => 'Cruise Operations'],
                ['key' => 'sector_touris_tourismmarketing', 'value' => 'Tourism Marketing'],
                ['key' => 'sector_touris_visitorinformationservices', 'value' => 'Visitor Information Services'],
                ['key' => 'sector_touris_indigenoustourism', 'value' => 'Indigenous Tourism'],
            ],
        ],
        [
            'industry' => 'industry_transport',
            'value' => 'Transport',
            'sectors' => [
                ['key' => 'sector_transp_roadtransport', 'value' => 'Road Transport'],
                ['key' => 'sector_transp_railtransport', 'value' => 'Rail Transport'],
                ['key' => 'sector_transp_maritimetransport', 'value' => 'Maritime Transport'],
                ['key' => 'sector_transp_airtransport', 'value' => 'Air Transport'],
                ['key' => 'sector_transp_publictransport', 'value' => 'Public Transport'],
                ['key' => 'sector_transp_taxiandrideshare', 'value' => 'Taxi & Rideshare'],
                ['key' => 'sector_transp_courierservices', 'value' => 'Courier Services'],
                ['key' => 'sector_transp_busoperations', 'value' => 'Bus Operations'],
                ['key' => 'sector_transp_heavyvehicleoperations', 'value' => 'Heavy Vehicle Operations'],
                ['key' => 'sector_transp_transportadministration', 'value' => 'Transport Administration'],
            ],
        ],
        [
            'industry' => 'industry_utilities',
            'value' => 'Utilities',
            'sectors' => [
                ['key' => 'sector_utilit_electricitygeneration', 'value' => 'Electricity Generation'],
                ['key' => 'sector_utilit_electricitydistribution', 'value' => 'Electricity Distribution'],
                ['key' => 'sector_utilit_gasdistribution', 'value' => 'Gas Distribution'],
                ['key' => 'sector_utilit_watersupply', 'value' => 'Water Supply'],
                ['key' => 'sector_utilit_wastewatertreatment', 'value' => 'Wastewater Treatment'],
                ['key' => 'sector_utilit_renewableenergy', 'value' => 'Renewable Energy'],
                ['key' => 'sector_utilit_smartgrid', 'value' => 'Smart Grid'],
                ['key' => 'sector_utilit_meterreading', 'value' => 'Meter Reading'],
                ['key' => 'sector_utilit_networkmaintenance', 'value' => 'Network Maintenance'],
            ],
        ],
        [
            'industry' => 'industry_warehousing',
            'value' => 'Warehousing',
            'sectors' => [
                ['key' => 'sector_wareho_generalwarehousing', 'value' => 'General Warehousing'],
                ['key' => 'sector_wareho_coldstorage', 'value' => 'Cold Storage'],
                ['key' => 'sector_wareho_distributioncentres', 'value' => 'Distribution Centres'],
                ['key' => 'sector_wareho_crossdocking', 'value' => 'Cross-Docking'],
                ['key' => 'sector_wareho_hazardousgoodsstorage', 'value' => 'Hazardous Goods Storage'],
                ['key' => 'sector_wareho_automatedwarehousing', 'value' => 'Automated Warehousing'],
                ['key' => 'sector_wareho_orderfulfillment', 'value' => 'Order Fulfillment'],
                ['key' => 'sector_wareho_returnsprocessing', 'value' => 'Returns Processing'],
                ['key' => 'sector_wareho_inventorycontrol', 'value' => 'Inventory Control'],
            ],
        ],
        [
            'industry' => 'industry_other',
            'value' => 'Other',
            'sectors' => [
                ['key' => 'sector_other_generalindustry', 'value' => 'General Industry'],
                ['key' => 'sector_other_crossindustry', 'value' => 'Cross-Industry'],
                ['key' => 'sector_other_emergingindustry', 'value' => 'Emerging Industry'],
            ],
        ],
    ];

    /**
     * Returns the taxonomy for the scenario options.
     *
     * Each entry carries the canonical English value, which is what the
     * generation service is sent, and the translated label shown to the user.
     *
     * @return array[] Array of ['value' => string, 'label' => string, 'sectors' => array[]].
     */
    public static function get_localised(): array {
        $result = [];

        foreach (self::KEYS as $industry) {
            $sectors = [];
            foreach ($industry['sectors'] as $sector) {
                $sectors[] = [
                    'value' => $sector['value'],
                    'label' => get_string($sector['key'], 'mod_aiactivities'),
                ];
            }

            $result[] = [
                'value' => $industry['value'],
                'label' => get_string($industry['industry'], 'mod_aiactivities'),
                'sectors' => $sectors,
            ];
        }

        return $result;
    }
}
