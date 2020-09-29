<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page preset filters.
 *
 * @group oe_list_pages
 */
class ListPagesPresetFiltersTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
  ];

  /**
   * Test presence of preset filters configuration in the plugin.
   */
  public function testListPageFiltersPresenceInContentForm(): void {
    // Default filters configuration is present in oe_list_page content type.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $this->assertSession()->fieldExists('Set default value for');

    // Create a new content type without preset filters functionality.
    $this->drupalCreateContentType([
      'type' => 'list_without_preset_filters',
      'name' => 'List without preset filters',
    ]);

    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'list_without_preset_filters',
    ]);

    $this->drupalGet('/node/add/list_without_preset_filters');
    $this->clickLink('List Page');
    $this->assertSession()->fieldNotExists('Set default value for');
  }

  /**
   * Test list page preset filters configuration.
   */
  public function testListPagePresetFilters(): void {
    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $date = new DrupalDateTime('30-10-2020');
    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_one',
      'body' => 'this is a cherry',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    // Field is present in oe_list_page.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->fillField('Title', 'List page for ct1');

    // Set preset filter for body.
    $page->selectFieldOption('Set default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $page->fillField('preset_filters_wrapper[edit][body][body]', 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry']);

    // Set preset filter for created.
    $page->selectFieldOption('Set default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $this->getSession()->getPage()->selectFieldOption('preset_filters_wrapper[edit][created][created_op]', 'After');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][created][created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry', 'Created' => 'After 19 October 2019']);

    // Set preset filter for published.
    $page->selectFieldOption('Set default value for', 'Published');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Published');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
    ]);

    // Remove preset filter for published.
    $page->selectFieldOption('Set default value for', 'Published');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Published');
    $page->pressButton('Remove default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry', 'Created' => 'After 19 October 2019']);
    $this->assertSession()->elementTextNotContains('css', '#list-page-default-filters table', 'Published');

    // Edit preset filter for body.
    $page->selectFieldOption('Set default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][body][body]', 'cherry');
    $page->fillField('preset_filters_wrapper[edit][body][body]', 'banana');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'banana', 'Created' => 'After 19 October 2019']);

    // Edit preset filter for created.
    $page->selectFieldOption('Set default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][created][created_op]', 'gt');
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][created][created_first_date_wrapper][created_first_date][date]', '2019-10-19');
    $this->getSession()->getPage()->selectFieldOption('preset_filters_wrapper[edit][created][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][created][created_first_date_wrapper][created_first_date][date]', '10/31/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'banana', 'Created' => 'Before 31 October 2020']);
    // Save.
    $page->pressButton('Save');
    // Check results.
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl());
    $assert->pageTextNotContains('that red fruit');
    $assert->pageTextContains('that yellow fruit');

    // Edit again, change preset filter, expose filter and save.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Set default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][body][body]', 'banana');
    $page->fillField('preset_filters_wrapper[edit][body][body]', 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry', 'Created' => 'Before 31 October 2020']);
    $page->checkField('Override default exposed filters');
    $page->checkField('Body');
    $page->checkField('Created');
    $page->pressButton('Save');
    $this->drupalGet($node->toUrl());
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $assert->fieldValueEquals('Body', 'cherry');
    $assert->fieldValueEquals('Created', 'lt');

    // Change directly the value in exposed form.
    $page->fillField('Body', 'banana');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $assert->fieldValueEquals('Body', 'banana');

    // Change also the created date.
    $this->getSession()->getPage()->selectFieldOption('Created', 'After');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/30/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->fieldValueEquals('Body', 'banana');
    $assert->fieldValueEquals('Created', 'gt');
    $assert->fieldValueEquals('created_first_date_wrapper[created_first_date][date]', '2020-10-30');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Edit again to remove filters and save.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Set default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][body][body]', 'cherry');
    $page->pressButton('Remove default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Created' => 'Before 31 October 2020']);
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Change preset filter for date.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Set default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Created');
    $page = $this->getSession()->getPage();
    $this->getSession()->getPage()->selectFieldOption('preset_filters_wrapper[edit][created][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][created][created_first_date_wrapper][created_first_date][date]', '10/30/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Created' => 'Before 30 October 2020']);
    $page->pressButton('Save');

    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
  }

  /**
   * Asserts the default value is set for the filter.
   *
   * @param array $default_filters
   *   The default filters.
   */
  protected function assertDefaultValueForFilters(array $default_filters = []): void {
    $assert = $this->assertSession();
    $assert->elementsCount('css', '#list-page-default-filters table tr', count($default_filters) + 1);
    foreach ($default_filters as $filter => $default_value) {
      $assert->elementTextContains('css', '#list-page-default-filters table', $filter);
      $assert->elementTextContains('css', '#list-page-default-filters table', $default_value);
    }
  }

}
