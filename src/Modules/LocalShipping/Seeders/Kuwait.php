<?php

namespace Space\Core\Modules\LocalShipping\Seeders;

use Space\Core\Modules\LocalShipping\AreasDB;

defined( 'ABSPATH' ) || exit;

/**
 * Kuwait delivery areas seed data.
 *
 * Each city has English and Arabic names.
 * Each area has English + Arabic names and a default delivery price (KWD).
 * All fees default to 0 — admin can adjust per-area after seeding.
 */
class Kuwait {

	/**
	 * Run the seeder — insert all cities and areas.
	 * Returns [ 'cities' => int, 'areas' => int ] counts.
	 */
	public static function seed(): array {
		$counts = [ 'cities' => 0, 'areas' => 0 ];

		foreach ( self::get() as $sort_order => $city_data ) {
			$city_id = AreasDB::insert_city( [
				'name'         => $city_data['name'],
				'country_code' => 'KW',
				'is_active'    => 1,
				'sort_order'   => $sort_order * 10,
			] );

			if ( ! $city_id ) {
				continue;
			}

			$counts['cities'] ++;

			foreach ( $city_data['areas'] as $area_sort => $area ) {
				$inserted = AreasDB::insert_area( [
					'city_id'            => $city_id,
					'name'               => [ 'en' => $area['en'], 'ar' => $area['ar'] ],
					'delivery_price'     => $area['price'] ?? 1.500,
					'express_fee'        => 0,
					'minimum_order'      => 0,
					'free_minimum_order' => 0,
					'is_active'          => 1,
					'sort_order'         => $area_sort * 10,
				] );

				if ( $inserted ) {
					$counts['areas'] ++;
				}
			}
		}

		return $counts;
	}

	public static function get(): array {
		return [
			[
				'name'  => [ 'en' => 'Kuwait City', 'ar' => 'مدينة الكويت' ],
				'areas' => [
					[ 'en' => 'Sharq', 'ar' => 'شرق', 'price' => 1.500 ],
					[ 'en' => 'Mirqab', 'ar' => 'المرقاب', 'price' => 1.500 ],
					[ 'en' => 'Dasman', 'ar' => 'دسمان', 'price' => 1.500 ],
					[ 'en' => 'Qibla', 'ar' => 'القبلة', 'price' => 1.500 ],
					[ 'en' => 'Bneid Al Gar', 'ar' => 'بنيد القار', 'price' => 1.500 ],
					[ 'en' => 'Abdullah Al Salem', 'ar' => 'عبدالله السالم', 'price' => 1.500 ],
					[ 'en' => 'Nuzha', 'ar' => 'النزهة', 'price' => 1.500 ],
					[ 'en' => 'Kaifan', 'ar' => 'كيفان', 'price' => 1.500 ],
					[ 'en' => 'Mansouriya', 'ar' => 'المنصورية', 'price' => 1.500 ],
					[ 'en' => 'Rawda', 'ar' => 'الروضة', 'price' => 1.500 ],
					[ 'en' => 'Adailiya', 'ar' => 'العديلية', 'price' => 1.500 ],
					[ 'en' => 'Faiha', 'ar' => 'الفيحاء', 'price' => 1.500 ],
					[ 'en' => 'Shuwaikh Industrial', 'ar' => 'الشويخ الصناعية', 'price' => 1.500 ],
					[ 'en' => 'Shuwaikh Residential', 'ar' => 'الشويخ السكنية', 'price' => 1.500 ],
				],
			],
			[
				'name'  => [ 'en' => 'Hawalli', 'ar' => 'حولي' ],
				'areas' => [
					[ 'en' => 'Hawalli', 'ar' => 'حولي', 'price' => 1.500 ],
					[ 'en' => 'Salmiya', 'ar' => 'السالمية', 'price' => 1.500 ],
					[ 'en' => 'Rumaithiya', 'ar' => 'الرميثية', 'price' => 1.500 ],
					[ 'en' => 'Bayan', 'ar' => 'بيان', 'price' => 1.500 ],
					[ 'en' => 'Mishref', 'ar' => 'مشرف', 'price' => 1.500 ],
					[ 'en' => 'Jabriya', 'ar' => 'الجابرية', 'price' => 1.500 ],
					[ 'en' => 'Salwa', 'ar' => 'سلوى', 'price' => 1.500 ],
					[ 'en' => 'Sha\'ab', 'ar' => 'الشعب', 'price' => 1.500 ],
					[ 'en' => 'Siddiq', 'ar' => 'الصديق', 'price' => 1.500 ],
					[ 'en' => 'Bidaa', 'ar' => 'البدع', 'price' => 1.500 ],
					[ 'en' => 'Hateen', 'ar' => 'حطين', 'price' => 1.500 ],
					[ 'en' => 'Mubarak Al Jabir', 'ar' => 'مبارك الجابر', 'price' => 1.500 ],
					[ 'en' => 'Anjafa', 'ar' => 'أنجفة', 'price' => 1.500 ],
				],
			],
			[
				'name'  => [ 'en' => 'Farwaniya', 'ar' => 'الفروانية' ],
				'areas' => [
					[ 'en' => 'Farwaniya', 'ar' => 'الفروانية', 'price' => 1.500 ],
					[ 'en' => 'Khaitan', 'ar' => 'خيطان', 'price' => 1.500 ],
					[ 'en' => 'Ardiya', 'ar' => 'العارضية', 'price' => 1.500 ],
					[ 'en' => 'Reggae', 'ar' => 'الرقعي', 'price' => 1.500 ],
					[ 'en' => 'Abdullah Al Mubarak', 'ar' => 'عبدالله المبارك', 'price' => 1.750 ],
					[ 'en' => 'Omariya', 'ar' => 'العمرية', 'price' => 1.500 ],
					[ 'en' => 'Sabah Al Nasser', 'ar' => 'صباح الناصر', 'price' => 1.500 ],
					[ 'en' => 'Ishbiliya', 'ar' => 'إشبيلية', 'price' => 1.500 ],
					[ 'en' => 'Rai', 'ar' => 'الري', 'price' => 1.500 ],
					[ 'en' => 'Andalus', 'ar' => 'الأندلس', 'price' => 1.500 ],
					[ 'en' => 'Rabiya', 'ar' => 'الرابية', 'price' => 1.500 ],
					[ 'en' => 'Firdous', 'ar' => 'الفردوس', 'price' => 1.500 ],
					[ 'en' => 'Jeleeb Al Shuyoukh', 'ar' => 'جليب الشيوخ', 'price' => 1.500 ],
					[ 'en' => 'Airport Road', 'ar' => 'طريق المطار', 'price' => 1.500 ],
				],
			],
			[
				'name'  => [ 'en' => 'Ahmadi', 'ar' => 'الأحمدي' ],
				'areas' => [
					[ 'en' => 'Ahmadi', 'ar' => 'الأحمدي', 'price' => 2.000 ],
					[ 'en' => 'Fahaheel', 'ar' => 'الفحيحيل', 'price' => 2.000 ],
					[ 'en' => 'Mahboula', 'ar' => 'المهبولة', 'price' => 2.000 ],
					[ 'en' => 'Abu Halifa', 'ar' => 'أبو حليفة', 'price' => 2.000 ],
					[ 'en' => 'Sabahiya', 'ar' => 'الصباحية', 'price' => 2.000 ],
					[ 'en' => 'Mangaf', 'ar' => 'المنقف', 'price' => 2.000 ],
					[ 'en' => 'Ali Sabah Al Salem', 'ar' => 'علي صباح السالم', 'price' => 2.000 ],
					[ 'en' => 'Fintas', 'ar' => 'الفنطاس', 'price' => 2.000 ],
					[ 'en' => 'Riqqa', 'ar' => 'الرقة', 'price' => 2.000 ],
					[ 'en' => 'Adan', 'ar' => 'عدان', 'price' => 2.000 ],
					[ 'en' => 'Hadiya', 'ar' => 'هدية', 'price' => 2.000 ],
					[ 'en' => 'Eqaila', 'ar' => 'العقيلة', 'price' => 2.000 ],
					[ 'en' => 'Mubarak Al Kabeer', 'ar' => 'مبارك الكبير', 'price' => 2.000 ],
					[ 'en' => 'Qurain', 'ar' => 'القرين', 'price' => 2.000 ],
					[ 'en' => 'Wafra', 'ar' => 'الوفرة', 'price' => 3.000 ],
					[ 'en' => 'Khiran', 'ar' => 'الخيران', 'price' => 3.000 ],
					[ 'en' => 'Sabah Al Ahmad', 'ar' => 'صباح الأحمد', 'price' => 3.000 ],
					[ 'en' => 'Khairan Resort', 'ar' => 'منتجع الخيران', 'price' => 3.000 ],
				],
			],
			[
				'name'  => [ 'en' => 'Mubarak Al Kabeer', 'ar' => 'مبارك الكبير' ],
				'areas' => [
					[ 'en' => 'Mubarak Al Kabeer', 'ar' => 'مبارك الكبير', 'price' => 1.750 ],
					[ 'en' => 'Sabah Al Salem', 'ar' => 'صباح السالم', 'price' => 1.750 ],
					[ 'en' => 'Qurain', 'ar' => 'القرين', 'price' => 1.750 ],
					[ 'en' => 'Fnaitees', 'ar' => 'الفنيطيس', 'price' => 1.750 ],
					[ 'en' => 'Abu Ftaira', 'ar' => 'أبو فطيرة', 'price' => 1.750 ],
					[ 'en' => 'Messila', 'ar' => 'المسيلة', 'price' => 1.750 ],
				],
			],
			[
				'name'  => [ 'en' => 'Jahra', 'ar' => 'الجهراء' ],
				'areas' => [
					[ 'en' => 'Jahra', 'ar' => 'الجهراء', 'price' => 2.500 ],
					[ 'en' => 'Sulaibiya', 'ar' => 'الصليبية', 'price' => 2.500 ],
					[ 'en' => 'Jahra Industrial', 'ar' => 'الجهراء الصناعية', 'price' => 2.500 ],
					[ 'en' => 'Qasr', 'ar' => 'القصر', 'price' => 2.500 ],
					[ 'en' => 'Naeem', 'ar' => 'النعيم', 'price' => 2.500 ],
					[ 'en' => 'Oyoun', 'ar' => 'العيون', 'price' => 2.500 ],
					[ 'en' => 'Amghara', 'ar' => 'أمغرة', 'price' => 2.500 ],
					[ 'en' => 'Waha', 'ar' => 'الواحة', 'price' => 2.500 ],
					[ 'en' => 'Taima', 'ar' => 'تيماء', 'price' => 2.500 ],
					[ 'en' => 'Kabed', 'ar' => 'كبد', 'price' => 3.000 ],
					[ 'en' => 'Abdali', 'ar' => 'العبدلي', 'price' => 3.500 ],
					[ 'en' => 'Jahra Farms', 'ar' => 'مزارع الجهراء', 'price' => 3.500 ],
				],
			],
		];
	}
}
