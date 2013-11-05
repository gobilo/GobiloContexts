<?php

namespace Gobilo\GobiloContexts;

use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Exception\PendingException;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Mink\Exception\UnsupportedDriverActionException;

use Drupal\Drupal;
use Drupal\Component\Utility\Random;
use Drupal\DrupalExtension\Event\EntityEvent;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;
use Drupal\DrupalExtension\Context\DrupalContext;

use Exception;
use Symfony\Component\Process\Process;
use Symfony\Component\EventDispatcher\EventDispatcher;

use Behat\Behat\Context\Step\Given;
use Behat\Behat\Context\Step\When;
use Behat\Behat\Context\Step\Then;
use Behat\Behat\Context\TranslatedContextInterface;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;

use Behat\Mink\Driver\Selenium2Driver as Selenium2Driver;

/**
 * Features context.
 */
class GobiloContext extends DrupalContext {

    /**
     * @Then /^the size of all "([^"]*)" should be \((\d+), (\d+)\)$/
     */
    public function theSizeOfAllShouldBe($element, $expected_width, $expected_height) {
        $els = $this->getSession()->getPage()->findAll('css', $element);
        if (!is_array($els)) {
            throw new \Exception(
                "No Element ' . $element . ' found!"
            );
        }
        foreach ($els AS $el) {
            $size = $el->getSize();

            if ($size['width'] !== (int) $expected_width
              || $size['height'] !== (int) $expected_height) {
                throw new \Exception(
                    "Actual size is:\n("
                    . $size['width'] .", " . $size['height'] . ")"
                );
            }
        }
    }

    /**
     * @Then /^the ratio of all "([^"]*)" should be between "([^"]*)" and "([^"]*)"$/
     */
    public function theRatioOfAllShouldBeBetweenAnd($element, $expected_min_ratio, $expected_max_ratio) {
        $els = $this->getSession()->getPage()->findAll('css', $element);
        if (!is_array($els)) {
            throw new \Exception(
                "No Element ' . $element . ' found!"
            );
        }
        $deviations = array();
        foreach ($els AS $key => $el) {
            $size = $el->getSize();
            $ratio = $size['width'] / $size['height'];
            $count = $key + 1;

            if ($ratio < $expected_min_ratio || $ratio > $expected_max_ratio) {
                $deviations[$count] = $ratio;
            }
        }
        $deviations_message = '';
        foreach ($deviations AS $number => $ratio) {
            $deviations_message .= "- Ratio of element number " . $number ." is ". $ratio . "\n";

        }
        if (count($deviations) > 0) {
            throw new \Exception(
                "Actual ratio isn't always conform with the expectations:\n" .
                $deviations_message
            );
        }
    }

    /**
     * @Given /^the window size \((\d+), (\d+)\)$/
     */
    public function setwindowSize($width, $height) {
        $this->getSession()->getDriver()->getWebDriverSession()->window('current')->postSize(array('width' => (int) $width, 'height' => (int) $height));
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * @Then /^take a screenshot from "([^"]*)" with window size \((\d+), (\d+)\)$/
     */
    public function takeAScreenshotFromWithWindowSize($path, $width, $height) {
        $this->getSession()->visit($this->locatePath($path));
        $this->getSession()->getDriver()->getWebDriverSession()->window('current')->postSize(array('width' => (int) $width, 'height' => (int) $height));

        // If this property is set takeScreenshotAfterScreenshotStep
        // will take action.
        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Take screenshot when takeAScreenshotFromWithWindowSize() was executed.
     *
     * Works only with Selenium2Driver.
     *
     * @AfterStep
     */
    public function takeScreenshotAfterScreenshotStep(stepEvent $event)
    {
        if (isset($this->path)) {
            $driver = $this->getSession()->getDriver();
            if (!($driver instanceof Selenium2Driver)) {
                throw new UnsupportedDriverActionException('Taking screenshots is not supported by %s, use Selenium2Driver instead.', $driver);
                return;
            }

            setlocale(LC_ALL, 'de_DE.UTF8');

            $capabilities = $driver->getDesiredCapabilities();
            $platform = $this->toAscii($capabilities['platform']);
            $browser = $this->toAscii($capabilities['browser']);
            $version = $this->toAscii($capabilities['version']);
            $feature = $this->toAscii($event->getStep()->getParent()->getFeature()->getTitle(),'"');
            $scenario = $this->toAscii($event->getStep()->getParent()->getTitle(),'"');
            //$step = $this->toAscii($event->getStep()->getText(), '"');

            $path = $this->toAscii($this->path) . '_';
            $directory = 'screenshots/'
              . $feature . '/' . $scenario . '/take-screenshots' . '/'
              . $platform . '-' . $browser . '-' .$version;
            if(!file_exists($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = $directory . '/'
              . $path
              . $this->width . 'x' . $this->height . '_'
              . date('Ymd_Hi')
              . '.png';

            $screenshot = $this->getSession()->getScreenshot();
            file_put_contents($filename, $screenshot);
        }
    }

    /**
     * Take screenshot when step fails.
     *
     * Works only with Selenium2Driver.
     *
     * @AfterStep
     */
    public function takeScreenshotAfterFailedStep(stepEvent $event)
    {
        $driver = $this->getSession()->getDriver();
        if (4 === $event->getResult() && $driver instanceof Selenium2Driver) {
            if (!($driver instanceof Selenium2Driver)) {
                throw new UnsupportedDriverActionException('Taking screenshots is not supported by %s, use Selenium2Driver instead.', $driver);
                return;
            }

            setlocale(LC_ALL, 'de_DE.UTF8');

            $capabilities = $driver->getDesiredCapabilities();
            $platform = $this->toAscii($capabilities['platform']);
            $browser = $this->toAscii($capabilities['browser']);
            $version = $this->toAscii($capabilities['version']);

            $feature = $event->getStep()->getParent()->getFeature()->getTitle();
            $comment_position = strpos($feature, "\n");
            if ($comment_position != 0) {
                $feature = substr($feature, 0, strpos($feature, "\n"));
            }
            $feature = $this->toAscii($feature);

            $scenario = $event->getStep()->getParent()->getTitle();
            $comment_position = strpos($scenario, "\n");
            if ($comment_position != 0) {
                $scenario = substr($scenario, 0, strpos($scenario, "\n"));
            }
            $scenario = $this->toAscii($scenario);

            $step = $this->toAscii($event->getStep()->getText(), '"');

            $directory = 'screenshots/'
              . $feature . '/' . $scenario . '/' . $step;

            if(!file_exists($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = $directory . '/'
              // . $step
              . $platform . '-' . $browser . '-' .$version .'_'
              . date('Ymd_Hi')
              . '.png';

            $screenshot = $this->getSession()->getScreenshot();
            file_put_contents($filename, $screenshot);
        }
    }

    /**
     * @Then /^the size of "([^"]*)" should be \((\d+), (\d+)\)$/
     */
    public function theSizeOfShouldBe($element, $expected_width, $expected_height) {
        $el = $this->getSession()->getPage()->find('css', $element);
        if (!($el instanceof NodeElement)) {
            throw new Exception(
                "Element ' . $element . ' not found!"
            );
        }
        $size = $el->getSize();

        if ($size['width'] !== (int) $expected_width
          || $size['height'] !== (int) $expected_height) {
            throw new Exception(
                "Actual size is:\n("
                . $size['width'] .", " . $size['height'] . ")"
            );
        }
    }

    /**
     * @Then /^the height of "([^"]*)" should be (\d+)$/
     */
    public function theHeightOfShouldBe($element, $expected_height) {
        $el = $this->getSession()->getPage()->find('css', $element);
        if (!($el instanceof NodeElement)) {
            throw new Exception(
                "Element ' . $element . ' not found!"
            );
        }
        $size = $el->getSize();

        if ($size['height'] !== (int) $expected_height) {
            throw new Exception(
                "Actual height is: " . $size['height']
            );
        }
    }

    /**
     * @Then /^the width of "([^"]*)" should be (\d+)$/
     */
    public function theWidthOfShouldBe($element, $expected_width) {
        $el = $this->getSession()->getPage()->find('css', $element);
        if (!($el instanceof NodeElement)) {
            throw new Exception(
                "Element ' . $element . ' not found!"
            );
        }
        $size = $el->getSize();

        if ($size['width'] !== (int) $expected_width) {
            throw new Exception(
                "Actual width is:\n" . $size['width']
            );
        }
    }

    /**
     * @Given /^the css value for "([^"]*)" of "([^"]*)" should be "([^"]*)"$/
     */
    public function theCssValueForOfShouldBe($property, $element, $expected_value) {
        $el = $this->getSession()->getPage()->find('css', $element);
        $value = $el->getCssValue($property);

        if ($value !== (string) $expected_value) {
            throw new Exception(
                "Actual value is: " . $value
            );
        }

        $screenshot = $this->getSession()->getScreenshot();
        $im = imagecreatefromstring($screenshot);
        if ($im !== false) {
            imagepng($im, 'screenshot_' . date('Ymd_Hi'). '.png');
            imagedestroy($im);
        }

    }

    /**
     * @Then /^the location of "([^"]*)" should be \((\d+), (\d+)\)$/
     */
    public function theLocationOfShouldBe($element, $expected_x, $expected_y) {
        $el = $this->getSession()->getPage()->find('css', $element);
        $location = $el->getLocation();

        if ($location['x'] !== (int) $expected_x ||
          $location['y'] !== (int) $expected_y) {
            throw new Exception(
                "Actual Location is:\n("
                . $location['x'] .", " . $location['y'] . ")"
            );
        }
    }

    /**
     * @When /^the window size is \((\d+), (\d+)\)$/
     */
    public function theWindowSizeIs($width, $height) {
        $this->getSession()->getDriver()->resizeWindow((int) $width, (int) $height);
    }

    /**
     * Convert title to ascii.
     *
     * @see http://cubiq.org/the-perfect-php-clean-url-generator
     *
     * @param string $str
     * @param array  $replace
     * @param string $delimiter
     *
     * @return mixed|string
     */
    function toAscii($str, $replace=array(), $delimiter='-') {
        if (!empty($replace)) {
            $str = str_replace($replace, '-', $str);
        }

        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $clean = preg_replace("/[^a-z.A-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim($clean, '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

        return $clean;
    }

}
