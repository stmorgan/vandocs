<?php

namespace Drupal\Tests\focal_point\Unit\Effects;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Image\ImageInterface;
use Drupal\crop\CropInterface;
use Drupal\crop\CropStorageInterface;
use Drupal\focal_point\Plugin\ImageEffect\FocalPointCropImageEffect;
use Drupal\focal_point\FocalPointEffectBase;
use Psr\Log\LoggerInterface;
use Drupal\Tests\focal_point\Unit\FocalPointUnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the Focal Point image effects.
 *
 * @group Focal Point
 *
 * @coversDefaultClass \Drupal\focal_point\FocalPointEffectBase
 */
class FocalPointEffectsTest extends FocalPointUnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * @covers ::__construct
   */
  public function testEffectConstructor() {
    $logger = $this->prophesize(LoggerInterface::class);
    $crop_storage = $this->prophesize(CropStorageInterface::class);
    $focal_point_config = $this->prophesize(ImmutableConfig::class);
    $request = $this->prophesize(Request::class);

    $effect = new FocalPointCropImageEffect([], 'plugin_id', [], $logger->reveal(), $this->focalPointManager, $crop_storage->reveal(), $focal_point_config->reveal(), $request->reveal());
    $this->assertAttributeEquals($crop_storage->reveal(), 'cropStorage', $effect);
    $this->assertAttributeEquals($focal_point_config->reveal(), 'focalPointConfig', $effect);
  }

  /**
   * @covers ::calculateResizeData
   *
   * @dataProvider calculateResizeDataProvider
   */
  public function testCalculateResizeData($image_width, $image_height, $crop_width, $crop_height, $expected) {
    $this->assertSame($expected, FocalPointEffectBase::calculateResizeData($image_width, $image_height, $crop_width, $crop_height));
  }

  /**
   * Data provider for testCalculateResizeData().
   *
   * @see FocalPointEffectsTest::testCalculateResizeData()
   */
  public function calculateResizeDataProvider() {
    $data = [];
    $data['horizontal_image_horizontal_crop'] = [640, 480, 300, 100, ['width' => 300, 'height' => 225]];
    $data['horizontal_image_vertical_crop'] = [640, 480, 100, 300, ['width' => 400, 'height' => 300]];
    $data['vertical_image_horizontal_crop'] = [480, 640, 300, 100, ['width' => 300, 'height' => 400]];
    $data['vertical_image_vertical_crop'] = [480, 640, 100, 300, ['width' => 225, 'height' => 300]];
    $data['horizontal_image_too_large_crop'] = [640, 480, 3000, 1000, ['width' => 3000, 'height' => 2250]];
    $data['image_too_narrow_to_crop_after_resize'] = [1920, 1080, 400, 300, ['width' => 533, 'height' => 300]];
    $data['image_too_short_to_crop_after_resize'] = [200, 400, 1000, 1000, ['width' => 1000, 'height' => 2000]];
    return $data;
  }

  /**
   *  @covers ::setOriginalImage
   *  @covers ::getOriginalImage
   */
  public function testSetGetOriginalImage() {
    $logger = $this->prophesize(LoggerInterface::class);
    $crop_storage = $this->prophesize(CropStorageInterface::class);
    $immutable_config = $this->prophesize(ImmutableConfig::class);
    $request = $this->prophesize(Request::class);

    $original_image = $this->prophesize(ImageInterface::class);
    $original_image = $original_image->reveal();

    $effect = new FocalPointCropImageEffect([], 'plugin_id', [], $logger->reveal(), $this->focalPointManager, $crop_storage->reveal(), $immutable_config->reveal(), $request->reveal());
    $effect->setOriginalImage($original_image);

    $this->assertEquals($original_image, $effect->getOriginalImage());
  }

  /**
   * @covers ::calculateAnchor
   *
   * @dataProvider calculateAnchorProvider
   */
  public function testCalculateAnchor($original_image_size, $resized_image_size, $cropped_image_size, $position, $expected_anchor) {
    $logger = $this->prophesize(LoggerInterface::class);
    $crop_storage = $this->prophesize(CropStorageInterface::class);
    $immutable_config = $this->prophesize(ImmutableConfig::class);
    $request = $this->prophesize(Request::class);

    $original_image = $this->prophesize(ImageInterface::class);
    $original_image->getWidth()->willReturn($original_image_size['width']);
    $original_image->getHeight()->willReturn($original_image_size['height']);

    $image = $this->prophesize(ImageInterface::class);
    $image->getWidth()->willReturn($resized_image_size['width']);
    $image->getHeight()->willReturn($resized_image_size['height']);

    $crop = $this->prophesize(CropInterface::class);
    $crop->position()->willReturn([
      'x' => $position['x'],
      'y' => $position['y'],
    ]);
    $crop->size()->willReturn([
      'width' => $cropped_image_size['width'],
      'height' => $cropped_image_size['height'],
    ]);

    // Use reflection to test a private/protected method.
    $effect = new TestFocalPointEffectBase([], 'plugin_id', [], $logger->reveal(), $this->focalPointManager, $crop_storage->reveal(), $immutable_config->reveal(), $request->reveal());
    $effect->setOriginalImage($original_image->reveal());
    $effect_reflection = new \ReflectionClass(TestFocalPointEffectBase::class);
    $method = $effect_reflection->getMethod('calculateAnchor');
    $method->setAccessible(TRUE);
    $this->assertSame($expected_anchor, $method->invokeArgs($effect, [$image->reveal(), $crop->reveal(), $original_image_size]));

    $effect->setTestingPreview(TRUE);
    $expected_anchor = ['x' => 0, 'y' => 0];
    $this->assertSame($expected_anchor, $method->invokeArgs($effect, [$image->reveal(), $crop->reveal(), $original_image_size]));

  }

  /**
   * Data provider for testCalculateAnchor().
   *
   * @see FocalPointEffectsTest::testCalculateAnchor()
   */
  public function calculateAnchorProvider() {
    $data = [];

    // Square image with square crop.
    $original_image_size = ['width' => 2000, 'height' => 2000];
    $resized_image_size = ['width' => 1000, 'height' => 1000];
    $cropped_image_size = ['width' => 1000, 'height' => 1000];
    list($top, $left, $center, $bottom, $right) = [100, 100, 1000, 1900, 1900];
    $data['square_image_with_square_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $center], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $center], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $center], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['square_image_with_square_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 0, 'y' => 0]];

    // Square image with horizontal crop.
    $original_image_size = ['width' => 2000, 'height' => 2000];
    $resized_image_size = ['width' => 1000, 'height' => 1000];
    $cropped_image_size = ['width' => 1000, 'height' => 250];
    list($top, $left, $center, $bottom, $right) = [100, 100, 1000, 1900, 1900];
    $data['square_image_with_horizontal_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_horizontal_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_horizontal_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_horizontal_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $center], ['x' => 0, 'y' => 375]];
    $data['square_image_with_horizontal_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $center], ['x' => 0, 'y' => 375]];
    $data['square_image_with_horizontal_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $center], ['x' => 0, 'y' => 375]];
    $data['square_image_with_horizontal_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 750]];
    $data['square_image_with_horizontal_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $bottom], ['x' => 0, 'y' => 750]];
    $data['square_image_with_horizontal_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 0, 'y' => 750]];

    // Square image with vertical crop.
    $original_image_size = ['width' => 2000, 'height' => 2000];
    $resized_image_size = ['width' => 500, 'height' => 500];
    $cropped_image_size = ['width' => 100, 'height' => 500];
    list($top, $left, $center, $bottom, $right) = [100, 100, 1000, 1900, 1900];
    $data['square_image_with_vertical_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['square_image_with_vertical_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $top], ['x' => 200, 'y' => 0]];
    $data['square_image_with_vertical_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 400, 'y' => 0]];
    $data['square_image_with_vertical_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $center], ['x' => 0, 'y' => 0]];
    $data['square_image_with_vertical_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $center], ['x' => 200, 'y' => 0]];
    $data['square_image_with_vertical_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $center], ['x' => 400, 'y' => 0]];
    $data['square_image_with_vertical_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['square_image_with_vertical_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $center, 'y' => $bottom], ['x' => 200, 'y' => 0]];
    $data['square_image_with_vertical_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 400, 'y' => 0]];

    // Horizontal image with square crop.
    $original_image_size = ['width' => 1500, 'height' => 500];
    $resized_image_size = ['width' => 600, 'height' => 200];
    $cropped_image_size = ['width' => 200, 'height' => 200];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [10, 10, 250, 750, 490, 1490];
    $data['horizontal_image_with_square_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_square_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 200, 'y' => 0]];
    $data['horizontal_image_with_square_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 400, 'y' => 0]];
    $data['horizontal_image_with_square_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_square_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 200, 'y' => 0]];
    $data['horizontal_image_with_square_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 400, 'y' => 0]];
    $data['horizontal_image_with_square_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_square_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 200, 'y' => 0]];
    $data['horizontal_image_with_square_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 400, 'y' => 0]];

    // Horizontal image with horizontal crop.
    $original_image_size = ['width' => 1024, 'height' => 768];
    $resized_image_size = ['width' => 1000, 'height' => 750];
    $cropped_image_size = ['width' => 800, 'height' => 50];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [10, 10, 384, 512, 750, 1000];
    $data['horizontal_image_with_horizontal_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_horizontal_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 100, 'y' => 0]];
    $data['horizontal_image_with_horizontal_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 200, 'y' => 0]];
    $data['horizontal_image_with_horizontal_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 350]];
    $data['horizontal_image_with_horizontal_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 100, 'y' => 350]];
    $data['horizontal_image_with_horizontal_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 200, 'y' => 350]];
    $data['horizontal_image_with_horizontal_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 700]];
    $data['horizontal_image_with_horizontal_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 100, 'y' => 700]];
    $data['horizontal_image_with_horizontal_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 200, 'y' => 700]];

    // Horizontal image with vertical crop.
    $original_image_size = ['width' => 1024, 'height' => 768];
    $resized_image_size = ['width' => 800, 'height' => 600];
    $cropped_image_size = ['width' => 313, 'height' => 600];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [10, 10, 384, 512, 750, 1000];
    $data['horizontal_image_with_vertical_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 243, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 487, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 243, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 487, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 243, 'y' => 0]];
    $data['horizontal_image_with_vertical_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 487, 'y' => 0]];

    // Vertical image with square crop.
    $original_image_size = ['width' => 500, 'height' => 2500];
    $resized_image_size = ['width' => 500, 'height' => 2500];
    $cropped_image_size = ['width' => 100, 'height' => 100];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [50, 50, 1250, 250, 2450, 450];
    $data['vertical_image_with_square_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['vertical_image_with_square_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 200, 'y' => 0]];
    $data['vertical_image_with_square_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 400, 'y' => 0]];
    $data['vertical_image_with_square_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 1200]];
    $data['vertical_image_with_square_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 200, 'y' => 1200]];
    $data['vertical_image_with_square_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 400, 'y' => 1200]];
    $data['vertical_image_with_square_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 2400]];
    $data['vertical_image_with_square_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 200, 'y' => 2400]];
    $data['vertical_image_with_square_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 400, 'y' => 2400]];

    // Vertical image with horizontal crop.
    $original_image_size = ['width' => 1111, 'height' => 313];
    $resized_image_size = ['width' => 1111, 'height' => 313];
    $cropped_image_size = ['width' => 400, 'height' => 73];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [10, 10, 384, 512, 750, 1000];
    $data['vertical_image_with_horizontal_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['vertical_image_with_horizontal_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 312, 'y' => 0]];
    $data['vertical_image_with_horizontal_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 711, 'y' => 0]];
    $data['vertical_image_with_horizontal_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 240]];
    $data['vertical_image_with_horizontal_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 312, 'y' => 240]];
    $data['vertical_image_with_horizontal_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 711, 'y' => 240]];
    $data['vertical_image_with_horizontal_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 240]];
    $data['vertical_image_with_horizontal_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 312, 'y' => 240]];
    $data['vertical_image_with_horizontal_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 711, 'y' => 240]];

    // Vertical image with vertical crop.
    $original_image_size = ['width' => 200, 'height' => 2000];
    $resized_image_size = ['width' => 112, 'height' => 1111];
    $cropped_image_size = ['width' => 100, 'height' => 1111];
    list($top, $left, $vcenter, $hcenter, $bottom, $right) = [10, 10, 384, 512, 750, 1000];
    $data['vertical_image_with_vertical_crop__top_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $top], ['x' => 0, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__top_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $top], ['x' => 12, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__top_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $top], ['x' => 12, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__center_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $vcenter], ['x' => 0, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__center_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $vcenter], ['x' => 12, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__center_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $vcenter], ['x' => 12, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__bottom_left'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $left, 'y' => $bottom], ['x' => 0, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__bottom_center'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $hcenter, 'y' => $bottom], ['x' => 12, 'y' => 0]];
    $data['vertical_image_with_vertical_crop__bottom_right'] = [$original_image_size, $resized_image_size, $cropped_image_size, ['x' => $right, 'y' => $bottom], ['x' => 12, 'y' => 0]];

    return $data;
  }

}

class TestFocalPointEffectBase extends FocalPointEffectBase {

  /**
   * @var bool
   */
  protected $testingPreview = FALSE;

  /**
   * @return null|string
   */
  protected function getPreviewValue() {
    return $this->testingPreview ? '0x0' : NULL;
  }

  /**
   * @param bool $testing_preview
   */
  public function setTestingPreview($testing_preview) {
    $this->testingPreview = $testing_preview;
  }
}
