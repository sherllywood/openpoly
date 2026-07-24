<?php
/**
 * Test: SetupWizard step labels and option keys.
 *
 * Renders full admin HTML, so the tests only cover the small,
 * non-WP-dependent pieces (constants + private step_label).
 *
 * @package OpenPoly
 */

declare(strict_types=1);

namespace OpenPoly\Tests\unit\Setup;

use OpenPoly\Language\LanguageManager;
use OpenPoly\Language\Repository;
use OpenPoly\Setup\SetupWizard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OpenPoly\Setup\SetupWizard
 */
final class SetupWizardTest extends TestCase {

	public function testOptionKeyConstantsAreStable(): void {
		self::assertSame( 'openpoly_settings', SetupWizard::SETTINGS_OPTION );
		self::assertSame( 'openpoly_wizard_skipped', SetupWizard::SKIPPED_OPTION );
		self::assertSame( 'openpoly_setup_wizard', SetupWizard::NONCE_ACTION );
	}

	public function testStepLabelMethodReturnsHumanReadableText(): void {
		$wizard = $this->make_wizard();

		$label_1 = $this->call_step_label( $wizard, 1 );
		$label_2 = $this->call_step_label( $wizard, 2 );
		$label_3 = $this->call_step_label( $wizard, 3 );
		$label_4 = $this->call_step_label( $wizard, 4 );

		self::assertNotSame( '', $label_1 );
		self::assertNotSame( '', $label_2 );
		self::assertNotSame( '', $label_3 );
		self::assertNotSame( '', $label_4 );
		self::assertNotSame( $label_1, $label_2 );
		self::assertNotSame( $label_2, $label_3 );
		self::assertNotSame( $label_3, $label_4 );
	}

	public function testStepLabelReturnsEmptyForUnknownStep(): void {
		$wizard = $this->make_wizard();

		self::assertSame( '', $this->call_step_label( $wizard, 0 ) );
		self::assertSame( '', $this->call_step_label( $wizard, 99 ) );
	}

	public function testConstructionAcceptsBothDependencies(): void {
		$languages = $this->createMock( LanguageManager::class );
		$repo      = $this->createMock( Repository::class );

		$wizard = new SetupWizard( $languages, $repo );

		self::assertInstanceOf( SetupWizard::class, $wizard );
	}

	/**
	 * Build a SetupWizard with mocked dependencies.
	 *
	 * @return SetupWizard
	 */
	private function make_wizard(): SetupWizard {
		$languages = $this->createMock( LanguageManager::class );
		$repo      = $this->createMock( Repository::class );
		return new SetupWizard( $languages, $repo );
	}

	/**
	 * Call the private step_label method via reflection.
	 *
	 * @param SetupWizard $wizard
	 * @param int         $step
	 * @return string
	 */
	private function call_step_label( SetupWizard $wizard, int $step ): string {
		$ref = new \ReflectionClass( $wizard );
		$m   = $ref->getMethod( 'step_label' );
		$m->setAccessible( true );
		return (string) $m->invoke( $wizard, $step );
	}
}
