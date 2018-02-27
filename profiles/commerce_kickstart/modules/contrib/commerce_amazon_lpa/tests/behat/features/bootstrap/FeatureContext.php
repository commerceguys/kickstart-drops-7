<?php

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  protected $originalWindowName = '';

  /**
   * Initializes context.
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }

  /**
   * @When /^(?:|I )click on Quick Edit link$/
   *
   * Click on Quick edit.
   */
  public function clickOnQuickEdit() {
    $this->getSession()->getPage()->clickLink('Quick edit');
    $this->getSession()->wait(5000, 'jQuery(".entity-commerce-order").length > 0');
  }

  /**
   * @Given /^(?:|I )wait(?:| for) (\d+) seconds?$/
   *
   * Wait for the given number of seconds. ONLY USE FOR DEBUGGING!
   */
  public function iWaitForSeconds($arg1) {
    sleep($arg1);
  }

  /**
   * @Given /^(?:|I )wait for AJAX loading to finish$/
   *
   * Wait for the jQuery AJAX loading to finish. ONLY USE FOR DEBUGGING!
   */
  public function iWaitForAJAX() {
    $this->getSession()->wait(5000, 'jQuery.active === 0');
  }

  /**
   * @When I resize the browser to mobile
   */
  public function iResizeTheBrowserToMobile() {
    $this->getSession()->resizeWindow(200, 600, 'current');
  }

  /**
   * @BeforeScenario
   */
  public function beforeScenario() {
    if (!$this->runningJavascript()) {
      return;
    }

    $current_driver = $this->getSession()->getDriver();
    if ($current_driver instanceof \Behat\Mink\Driver\Selenium2Driver) {
    }

    $this->getSession()->executeScript('window.name = "main_window"');
    $this->getSession()->resizeWindow(1440, 900, 'current');

    $originalWindowName = $this->getSession()->getWindowName(); //Get the original name

    if (empty($this->originalWindowName)) {
      $this->originalWindowName = $originalWindowName;
    }
    print $this->originalWindowName;
  }

  /**
   * Returns whether the scenario is running in a browser that can run Javascript or not.
   *
   * @return boolean
   */
  protected function runningJavascript() {
    return get_class($this->getSession()
      ->getDriver()) !== 'Behat\Mink\Driver\GoutteDriver';
  }

  /**
   * @Given I wait for Amazon to load
   */
  public function amazonIsLoaded() {
    $this->getSession()->wait(5000, 'typeof window.amazon !== "undefined"');
  }

  /**
   * @Given I wait for the Amazon order reference
   */
  public function amazonOrderReference() {
    $this->getSession()->wait(5000, "document.getElementsByName('commerce_amazon_lpa_contract_id[reference_id]')[0].value !== ''");
  }

  /**
   * @Then /^I switch to popup$/
   */
  public function iSwitchToPopup() {
    $popupName = $this->getNewPopup($this->originalWindowName);

    //Switch to the popup Window
    $this->getSession()->switchToWindow($popupName);
  }

  /**
   * @Then /^I switch back to original window$/
   */
  public function iSwitchBackToOriginalWindow() {
    //Switch to the original window
    $this->getSession()->switchToWindow($this->originalWindowName);
  }

  /**
   * This gets the window name of the new popup.
   */
  private function getNewPopup($originalWindowName = NULL) {
    //Get all of the window names first
    $names = $this->getSession()->getWindowNames();

    //Now it should be the last window name
    $last = array_pop($names);

    if (!empty($originalWindowName)) {
      while ($last == $originalWindowName && !empty($names)) {
        $last = array_pop($names);
      }
    }

    return $last;
  }

  /**
   * @When I click the Login with Amazon button
   */
  function clickLwAButton() {
    $div = $this->getSession()->getPage()->find('xpath', '//div[@id="amazonloginbutton"]//img');
    if ($div === null) {
      throw new ElementNotFoundException($this->getSession(), 'amazonloginbutton', 'xpath', '//div[@id="amazonloginbutton"]');
    }
    $div->click();
  }

  /**
   * @When I click the cart summary Pay with Amazon button
   */
  function clickCartSummaryPwAButton() {
    $div = $this->getSession()->getPage()->find('xpath', '//div[starts-with(@id, \'amazon-checkout-summary-link\')]//img');
    if ($div === null) {
      throw new ElementNotFoundException($this->getSession(), 'amazonloginbutton', 'xpath', '//div[starts-with(@id, \'amazon-checkout-summary-link\')]//img');
    }
    $div->click();
  }

  /**
   * @When I click the Pay with Amazon button
   */
  function clickPwAButton() {
    $div = $this->getSession()->getPage()->find('xpath', '//div[starts-with(@id, "amazon_lpa_cart_pay")]//img');
    if ($div === null) {
      throw new ElementNotFoundException($this->getSession(), 'amazonloginbutton', 'xpath', '//div[starts-with(@id, \'amazon-checkout-summary-link\')]//img');
    }
    $div->click();
  }

  /**
   * @Then I am on the checkout form
   */
  function thenIAmOnTheCheckoutForm() {
    $form = $this->getSession()->getPage()->find('xpath', '//form[@id="commerce-checkout-form-checkout"]');
    if ($form === null) {
      throw new ElementNotFoundException($this->getSession(), 'amazonloginbutton', 'xpath', '//div[starts-with(@id, \'amazon-checkout-summary-link\')]//img');
    }
  }

  /**
   * @When the Amazon address book exists
   */
  function amazonAddressBookExists() {
    $addressbook_pane = $this->getSession()->getPage()->find('xpath', '//div[@id="checkout-amazon-addressbook"]');
    if ($addressbook_pane === null) {
      throw new ElementNotFoundException($this->getSession());
    }
    $addressbook_iframe = $this->getSession()->getPage()->find('xpath', '//div[@id="checkout-amazon-addressbook"]//iframe');
    if ($addressbook_iframe === null) {
      throw new ElementNotFoundException($this->getSession());
    }
  }

  /**
   * @When the Amazon address book does not exist
   */
  function amazonAddressBookNotExists() {
    $addressbook_pane = $this->getSession()->getPage()->find('xpath', '//div[@id="checkout-amazon-addressbook"]');
    if ($addressbook_pane !== null) {
      throw new ElementNotFoundException($this->getSession());
    }
  }

  /**
   * @When the Amazon address book review exists
   */
  function amazonAddressBookReviewExists() {
    $addressbook_pane = $this->getSession()->getPage()->find('xpath', '//div[@id="customer-profile-shipping"]');
    if ($addressbook_pane === null) {
      throw new ElementNotFoundException($this->getSession());
    }
    $addressbook_iframe = $this->getSession()->getPage()->find('xpath', '//div[@id="customer-profile-shipping"]//iframe');
    if ($addressbook_iframe === null) {
      throw new ElementNotFoundException($this->getSession());
    }
  }

  /**
   * @When the Amazon address book review does not exist
   */
  function amazonAddressBookReviewNotExists() {
    $addressbook_pane = $this->getSession()->getPage()->find('xpath', '//div[@id="customer-profile-shipping"]');
    if ($addressbook_pane !== null) {
      throw new ElementNotFoundException($this->getSession());
    }
  }

  /**
   * @When the Amazon wallet exists
   */
  function amazonWalletExists() {
    $addressbook_pane = $this->getSession()->getPage()->find('xpath', '//div[@id="walletwidgetdiv"]');
    if ($addressbook_pane === null) {
      throw new ElementNotFoundException($this->getSession());
    }
    $addressbook_iframe = $this->getSession()->getPage()->find('xpath', '//div[@id="walletwidgetdiv"]//iframe');
    if ($addressbook_iframe === null) {
      throw new ElementNotFoundException($this->getSession());
    }
  }

  /**
   * @Then I set the Amazon setting :name to :value
   */
  function setAmazonCheckoutSetting($name, $value) {
    /** @var \Drupal\Driver\DrushDriver $drush */
    $drush = $this->getDriver('drush');

    if (is_bool($value)) {
      $value = (bool) $value;
    }

    $drush->drush("variable-set $name $value");
  }

  /**
   * @Then I set the Amazon setting array :name to :value
   */
  function setAmazonCheckoutArraySetting($name, $value) {
    /** @var \Drupal\Driver\DrushDriver $drush */
    $drush = $this->getDriver('drush');
    $output = $drush->drush("variable-set", array($name, sprintf("'%s'", $value)), array('format' => 'json'));
  }

  /**
   * @When an Amazon order has been created
   */
  public function createAmazonOrder() {
    $this->setAmazonCheckoutArraySetting('commerce_amazon_lpa_popup', 'redirect');
    $this->setAmazonCheckoutArraySetting('commerce_amazon_lpa_authorization_mode', 'automatic_sync');
    $this->setAmazonCheckoutArraySetting('commerce_amazon_lpa_capture_mode', 'shipment_capture');

    $this->visitPath('/storage-devices/commerce-guys-usb-key');
    $this->getSession()->getPage()->pressButton('Add to cart');

    $this->visitPath('/cart');
    $this->amazonIsLoaded();
    $this->clickPwAButton();
    $this->iSwitchToPopup();
    $this->getSession()->getPage()->fillField('email', 'matt+1@commerceguys.com');
    $this->getSession()->getPage()->fillField('password', 'password');
    $this->getSession()->getPage()->pressButton('Sign in using our secure server');
    $this->iSwitchBackToOriginalWindow();

    $this->thenIAmOnTheCheckoutForm();
    $this->amazonIsLoaded();
    $this->amazonOrderReference();
    $this->amazonAddressBookExists();
    $this->getSession()->getPage()->pressButton('Continue to next step');
    $this->getSession()->getPage()->pressButton('Continue to next step');
    $this->amazonIsLoaded();
    $this->iWaitForSeconds(3);
    $this->amazonWalletExists();
    $this->amazonAddressBookReviewExists();
    $this->getSession()->getPage()->pressButton('Continue to next step');
    $this->assertSession()->pageTextContains('Checkout complete');
  }
}
