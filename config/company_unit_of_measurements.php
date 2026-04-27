<?php

/**
 * Default Units of Measure seeded for every new Company.
 *
 * Each entry will be inserted into the `units_of_measure` table upon
 * Company creation. Companies can add, edit, or remove UoMs afterwards.
 *
 * @var array<int, array{name: string, abbreviation: string}>
 */
return [
    ['name_en' => 'Piece',      'name_ar' => 'قطعة',   'abbreviation_en' => 'Pcs', 'abbreviation_ar' => 'قطعة'],
    ['name_en' => 'Kilogram',   'name_ar' => 'كيلوجرام', 'abbreviation_en' => 'KG',  'abbreviation_ar' => 'كجم'],
    ['name_en' => 'Gram',       'name_ar' => 'جرام',    'abbreviation_en' => 'g',   'abbreviation_ar' => 'جم'],
    ['name_en' => 'Liter',      'name_ar' => 'لتر',     'abbreviation_en' => 'L',   'abbreviation_ar' => 'لتر'],
    ['name_en' => 'Milliliter',  'name_ar' => 'ملليلتر',  'abbreviation_en' => 'mL',  'abbreviation_ar' => 'مل'],
    ['name_en' => 'Meter',      'name_ar' => 'متر',     'abbreviation_en' => 'm',   'abbreviation_ar' => 'م'],
    ['name_en' => 'Box',        'name_ar' => 'صندوق',   'abbreviation_en' => 'Box', 'abbreviation_ar' => 'صندوق'],
    ['name_en' => 'Carton',     'name_ar' => 'كرتونة',  'abbreviation_en' => 'Ctn', 'abbreviation_ar' => 'كرتون'],
    ['name_en' => 'Bag',        'name_ar' => 'حقيبة',   'abbreviation_en' => 'Bag', 'abbreviation_ar' => 'حقيبة'],
    ['name_en' => 'Dozen',      'name_ar' => 'دستة',    'abbreviation_en' => 'Dz',  'abbreviation_ar' => 'دستة'],
];
