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

use Symfony\Component\Process\Process;
use Symfony\Component\EventDispatcher\EventDispatcher;

use Behat\Behat\Context\Step\Given;
use Behat\Behat\Context\Step\When;
use Behat\Behat\Context\Step\Then;
use Behat\Behat\Context\TranslatedContextInterface;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

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

}
