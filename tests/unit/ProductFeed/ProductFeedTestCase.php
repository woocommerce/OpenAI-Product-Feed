<?php
declare( strict_types = 1 );

namespace Automattic\WooCommerce\ProductFeedForOpenAI;

use PHPUnit\Framework\MockObject\MockObject;
use WC_Unit_Test_Case;

/**
 * ProductFeed test case class.
 */
class ProductFeedTestCase extends WC_Unit_Test_Case {
	/**
	 * Creates a mock object.
	 *
	 * This method does not work differently from `createMock`,
	 * but the DocBlock comment indicates a proper return type,
	 * combining `MockObject` and the provided class name.
	 *
	 * @template ID
	 * @param class-string<ID> $original_class_name Name of the class to mock.
	 * @return ID|MockObject
	 */
	// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found,Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function createMock( string $original_class_name ): MockObject {
		return parent::createMock( $original_class_name );
	}
}
