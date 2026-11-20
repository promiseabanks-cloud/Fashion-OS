<?php
/**
 * Default production stages.
 *
 * @package FashionOS
 */

declare( strict_types=1 );

namespace FashionOS\Production;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starter workflow stages for a new workspace.
 *
 * These are examples from the specification, not fixed universal stages: a
 * workspace may rename, reorder, deactivate or add its own through the stages
 * API. They are seeded once, when a workspace first asks for its stages, so a
 * new workspace can create a job immediately without an extra setup step.
 */
final class Stages {

	/**
	 * Default stages in workflow order.
	 *
	 * @return array<int, array{name: string, slug: string, sequence: int}>
	 */
	public static function defaults(): array {
		return array(
			array(
				'name'     => __( 'Design confirmation', 'fashionos' ),
				'slug'     => 'design-confirmation',
				'sequence' => 10,
			),
			array(
				'name'     => __( 'Material preparation', 'fashionos' ),
				'slug'     => 'material-preparation',
				'sequence' => 20,
			),
			array(
				'name'     => __( 'Cutting', 'fashionos' ),
				'slug'     => 'cutting',
				'sequence' => 30,
			),
			array(
				'name'     => __( 'Sewing', 'fashionos' ),
				'slug'     => 'sewing',
				'sequence' => 40,
			),
			array(
				'name'     => __( 'Finishing', 'fashionos' ),
				'slug'     => 'finishing',
				'sequence' => 50,
			),
			array(
				'name'     => __( 'Fitting', 'fashionos' ),
				'slug'     => 'fitting',
				'sequence' => 60,
			),
			array(
				'name'     => __( 'Alterations', 'fashionos' ),
				'slug'     => 'alterations',
				'sequence' => 70,
			),
			array(
				'name'     => __( 'Quality control', 'fashionos' ),
				'slug'     => 'quality-control',
				'sequence' => 80,
			),
			array(
				'name'     => __( 'Ready for collection', 'fashionos' ),
				'slug'     => 'ready-for-collection',
				'sequence' => 90,
			),
		);
	}
}
