<?php

namespace Drupal\shloka\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\book\BookManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Service for managing book structures.
 */
class BookStructureManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The book manager.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AliasManagerInterface $alias_manager,
    ConfigFactoryInterface $config_factory,
    BookManagerInterface $book_manager,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->aliasManager = $alias_manager;
    $this->configFactory = $config_factory;
    $this->bookManager = $book_manager;
    $this->logger = $logger_factory->get('shloka');
  }

  /**
   * Create chapters for a book.
   */
  public function createChapters(string $book_type, array $config): void {
    $settings = $this->configFactory->get('shloka.settings')->get('structure');
    $book_config = $settings[$book_type] ?? [];
    
    if (empty($book_config['main_book_nid'])) {
      throw new \Exception("Main book node ID not configured for {$book_type}");
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $main_book = $storage->load($book_config['main_book_nid']);
    
    if (!$main_book) {
      throw new \Exception("Main book node not found for {$book_type}");
    }

    $chapter_count = $config['chapters'] ?? $book_config['chapters'] ?? 0;
    
    for ($i = 1; $i <= $chapter_count; $i++) {
      $chapter = $storage->create([
        'type' => $book_type . '_chapter',
        'title' => $this->getChapterTitle($book_type, $i),
        'field_number' => $i,
        'status' => 1,
      ]);
      
      // Set book reference
      if ($book_type === 'bg') {
        $chapter->set('field_book_ref', $main_book->id());
      }
      
      // Configure book outline
      $chapter->book = [
        'bid' => $main_book->book['bid'] ?? $main_book->id(),
        'pid' => $main_book->id(),
        'has_children' => 1,
        'weight' => $i,
      ];
      
      $chapter->save();
      
      // Create path alias
      $alias = "/books/{$book_type}/{$i}";
      $this->createPathAlias($chapter, $alias);
      
      $this->logger->info('Created chapter @num for @type', [
        '@num' => $i,
        '@type' => $book_type,
      ]);
    }
  }

  /**
   * Create songs for Srimad Bhagavatam.
   */
  public function createSongs(): void {
    $settings = $this->configFactory->get('shloka.settings')->get('structure.sb');
    
    if (empty($settings['main_book_nid'])) {
      throw new \Exception("Main book node ID not configured for SB");
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $main_book = $storage->load($settings['main_book_nid']);
    
    if (!$main_book) {
      throw new \Exception("Main book node not found for SB");
    }

    for ($i = 1; $i <= 12; $i++) {
      $song = $storage->create([
        'type' => 'sb_song',
        'title' => $this->t('Песнь @num', ['@num' => $i]),
        'field_number' => $i,
        'field_title' => $this->t('Песнь @num', ['@num' => $i]),
        'status' => 1,
      ]);
      
      // Configure book outline
      $song->book = [
        'bid' => $main_book->book['bid'] ?? $main_book->id(),
        'pid' => $main_book->id(),
        'has_children' => 1,
        'weight' => $i,
      ];
      
      $song->save();
      
      // Create path alias
      $alias = "/books/sb/{$i}";
      $this->createPathAlias($song, $alias);
      
      // Create chapters for this song
      $chapter_count = $settings['chapters'][$i] ?? 0;
      for ($j = 1; $j <= $chapter_count; $j++) {
        $chapter = $storage->create([
          'type' => 'sb_chapter',
          'title' => $this->t('Глава @num', ['@num' => $j]),
          'field_number' => $j,
          'field_song_ref' => $song->id(),
          'status' => 1,
        ]);
        
        // Configure book outline
        $chapter->book = [
          'bid' => $main_book->book['bid'] ?? $main_book->id(),
          'pid' => $song->id(),
          'has_children' => 1,
          'weight' => $j,
        ];
        
        $chapter->save();
        
        // Create path alias
        $alias = "/books/sb/{$i}/{$j}";
        $this->createPathAlias($chapter, $alias);
      }
      
      $this->logger->info('Created song @num with @chapters chapters', [
        '@num' => $i,
        '@chapters' => $chapter_count,
      ]);
    }
  }

  /**
   * Create lilas for Chaitanya Charitamrita.
   */
  public function createLilas(): void {
    $settings = $this->configFactory->get('shloka.settings')->get('structure.cc');
    
    if (empty($settings['main_book_nid'])) {
      throw new \Exception("Main book node ID not configured for CC");
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $main_book = $storage->load($settings['main_book_nid']);
    
    if (!$main_book) {
      throw new \Exception("Main book node not found for CC");
    }

    $lilas = [
      1 => ['name' => 'adi', 'title' => 'Ади-лила'],
      2 => ['name' => 'madhya', 'title' => 'Мадхья-лила'],
      3 => ['name' => 'antya', 'title' => 'Антья-лила'],
    ];

    foreach ($lilas as $num => $lila_info) {
      $lila = $storage->create([
        'type' => 'cc_lila',
        'title' => $lila_info['title'],
        'field_number' => $num,
        'field_title' => $lila_info['title'],
        'status' => 1,
      ]);
      
      // Configure book outline
      $lila->book = [
        'bid' => $main_book->book['bid'] ?? $main_book->id(),
        'pid' => $main_book->id(),
        'has_children' => 1,
        'weight' => $num,
      ];
      
      $lila->save();
      
      // Create path alias
      $alias = "/books/cc/{$lila_info['name']}";
      $this->createPathAlias($lila, $alias);
      
      // Create chapters for this lila
      $chapter_count = $settings['chapters'][$lila_info['name']] ?? 0;
      for ($j = 1; $j <= $chapter_count; $j++) {
        $chapter = $storage->create([
          'type' => 'cc_chapter',
          'title' => $this->t('Глава @num', ['@num' => $j]),
          'field_number' => $j,
          'field_lila_ref' => $lila->id(),
          'status' => 1,
        ]);
        
        // Configure book outline
        $chapter->book = [
          'bid' => $main_book->book['bid'] ?? $main_book->id(),
          'pid' => $lila->id(),
          'has_children' => 1,
          'weight' => $j,
        ];
        
        $chapter->save();
        
        // Create path alias
        $alias = "/books/cc/{$lila_info['name']}/{$j}";
        $this->createPathAlias($chapter, $alias);
      }
      
      $this->logger->info('Created lila @name with @chapters chapters', [
        '@name' => $lila_info['title'],
        '@chapters' => $chapter_count,
      ]);
    }
  }

  /**
   * Assign verses to chapters.
   */
  public function assignVersesToChapters(string $book_type): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $database = \Drupal::database();
    
    // Load all verses
    $verse_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $book_type)
      ->execute();
    
    $verses = $storage->loadMultiple($verse_ids);
    
    foreach ($verses as $verse) {
      // Get alias directly from database
      $alias = $database->select('path_alias', 'pa')
        ->fields('pa', ['alias'])
        ->condition('path', '/node/' . $verse->id())
        ->condition('status', 1)
        ->execute()
        ->fetchField();
      
      if (!$alias) {
        $this->logger->warning('No alias found for verse @id', ['@id' => $verse->id()]);
        continue;
      }
      
      $this->logger->info('Processing verse @id with alias @alias', [
        '@id' => $verse->id(),
        '@alias' => $alias,
      ]);
      
      // Parse chapter from alias
      $chapter_num = $this->parseChapterFromAlias($alias, $book_type);
      
      if ($chapter_num) {
        $chapter = null;
        
        // For SB, we need to find chapter by song AND chapter number
        if ($book_type === 'sb') {
          $song_num = $this->parseSongFromAlias($alias);
          if ($song_num) {
            $chapter = $this->findSbChapter($song_num, $chapter_num);
          }
        }
        // For CC, we need to find chapter by lila AND chapter number
        elseif ($book_type === 'cc') {
          $lila_name = $this->parseLilaFromAlias($alias);
          if ($lila_name) {
            $chapter = $this->findCcChapter($lila_name, $chapter_num);
          }
        }
        // For BG, simple chapter lookup
        else {
          $chapter = $this->findChapter($book_type, $chapter_num);
        }
        
        if ($chapter) {
          // Get verse number from alias
          $verse_num = $this->parseVerseFromAlias($alias);
          
          // Remove existing book entry directly from database
          $database = \Drupal::database();
          try {
            $database->delete('book')
              ->condition('nid', $verse->id())
              ->execute();
          } catch (\Exception $e) {
            // Log but continue - record might not exist
            $this->logger->debug('No existing book entry for verse @id', ['@id' => $verse->id()]);
          }
          
          // Create new book entry
          $verse->book = [
            'bid' => $chapter->book['bid'],
            'pid' => $chapter->id(),
            'has_children' => 0,
            'weight' => $verse_num,
          ];
          
          try {
            $verse->save();
            
            $this->logger->info('Assigned verse @id to chapter @chapter', [
              '@id' => $verse->id(),
              '@chapter' => $chapter->id(),
            ]);
          } catch (\Exception $e) {
            $this->logger->warning('Failed to assign verse @id: @error', [
              '@id' => $verse->id(),
              '@error' => $e->getMessage(),
            ]);
          }
        }
        else {
          $this->logger->warning('Chapter not found for verse @id with chapter num @num', [
            '@id' => $verse->id(),
            '@num' => $chapter_num,
          ]);
        }
      }
    }
  }

  /**
   * Delete all structure for a book type.
   */
  public function deleteStructure(string $book_type): void {
    $storage = $this->entityTypeManager->getStorage('node');
    
    // Delete chapters
    $chapter_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $book_type . '_chapter')
      ->execute();
    
    if ($chapter_ids) {
      $chapters = $storage->loadMultiple($chapter_ids);
      $storage->delete($chapters);
    }
    
    // Delete songs/lilas if applicable
    if ($book_type === 'sb') {
      $song_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'sb_song')
        ->execute();
      
      if ($song_ids) {
        $songs = $storage->loadMultiple($song_ids);
        $storage->delete($songs);
      }
    }
    elseif ($book_type === 'cc') {
      $lila_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'cc_lila')
        ->execute();
      
      if ($lila_ids) {
        $lilas = $storage->loadMultiple($lila_ids);
        $storage->delete($lilas);
      }
    }
    
    // Remove verses from book outline
    $verse_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $book_type)
      ->execute();
    
    $verses = $storage->loadMultiple($verse_ids);
    foreach ($verses as $verse) {
      $verse->book = [];
      $verse->save();
    }
    
    $this->logger->info('Deleted structure for @type', ['@type' => $book_type]);
  }

  /**
   * Sync with menu_tree module.
   */
  public function syncWithMenutree(): void {
    // Get all book nodes
    $storage = $this->entityTypeManager->getStorage('node');
    $menu_link_content_storage = $this->entityTypeManager->getStorage('menu_link_content');
    
    $book_types = ['bg', 'sb', 'cc'];
    $config = $this->configFactory->get('shloka.settings');
    $menu_name = $config->get('menu.machine_name');
    
    foreach ($book_types as $book_type) {
      $main_book_nid = $config->get("structure.{$book_type}.main_book_nid");
      if (!$main_book_nid) {
        continue;
      }
      
      $main_book = $storage->load($main_book_nid);
      if (!$main_book || empty($main_book->book['bid'])) {
        continue;
      }
      
      // Get book tree
      $book_tree = $this->bookManager->bookTreeAllData($main_book->book['bid']);
      
      // Create menu links for book structure
      $this->createMenuLinksFromBookTree($book_tree, $menu_name, null);
    }
    
    $this->logger->info('Menu synchronized with book structure');
  }
  
  /**
   * Create menu links from book tree.
   */
  protected function createMenuLinksFromBookTree(array $tree, string $menu_name, $parent_id = null): void {
    $menu_link_content_storage = $this->entityTypeManager->getStorage('menu_link_content');
    
    foreach ($tree as $item) {
      if (empty($item['link']['nid'])) {
        continue;
      }
      
      $node = $this->entityTypeManager->getStorage('node')->load($item['link']['nid']);
      if (!$node) {
        continue;
      }
      
      // Check if menu link already exists
      $existing = $menu_link_content_storage->loadByProperties([
        'menu_name' => $menu_name,
        'link.uri' => 'entity:node/' . $node->id(),
      ]);
      
      if (!$existing) {
        $menu_link = $menu_link_content_storage->create([
          'title' => $node->getTitle(),
          'link' => ['uri' => 'entity:node/' . $node->id()],
          'menu_name' => $menu_name,
          'parent' => $parent_id ? 'menu_link_content:' . $parent_id : '',
          'weight' => $item['link']['weight'] ?? 0,
          'expanded' => !empty($item['below']),
        ]);
        $menu_link->save();
        $parent_id_for_children = $menu_link->uuid();
      } else {
        $existing_link = reset($existing);
        $parent_id_for_children = $existing_link->uuid();
      }
      
      // Process children
      if (!empty($item['below'])) {
        $this->createMenuLinksFromBookTree($item['below'], $menu_name, $parent_id_for_children);
      }
    }
  }

  /**
   * Helper to get chapter title.
   */
  protected function getChapterTitle(string $book_type, int $number): string {
    return $this->t('Глава @num', ['@num' => $number]);
  }

  /**
   * Helper to create path alias.
   */
  protected function createPathAlias(NodeInterface $node, string $alias): void {
    $path_alias_storage = $this->entityTypeManager->getStorage('path_alias');
    
    // Check if alias already exists
    $existing = $path_alias_storage->loadByProperties([
      'alias' => $alias,
    ]);
    
    if (!$existing) {
      $path_alias = $path_alias_storage->create([
        'path' => '/node/' . $node->id(),
        'alias' => $alias,
        'langcode' => $node->language()->getId(),
      ]);
      $path_alias->save();
    }
  }

  /**
   * Parse chapter number from alias.
   */
  protected function parseChapterFromAlias(string $alias, string $book_type): ?int {
    if ($book_type === 'bg') {
      if (preg_match('/\/books\/bg\/(\d+)\//', $alias, $matches)) {
        return (int) $matches[1];
      }
    }
    elseif ($book_type === 'sb') {
      if (preg_match('/\/books\/sb\/\d+\/(\d+)\//', $alias, $matches)) {
        return (int) $matches[1];
      }
    }
    elseif ($book_type === 'cc') {
      if (preg_match('/\/books\/cc\/[^\/]+\/(\d+)\//', $alias, $matches)) {
        return (int) $matches[1];
      }
    }
    
    return null;
  }
  
  /**
   * Parse song number from SB alias.
   */
  protected function parseSongFromAlias(string $alias): ?int {
    if (preg_match('/\/books\/sb\/(\d+)\//', $alias, $matches)) {
      return (int) $matches[1];
    }
    return null;
  }
  
  /**
   * Parse lila name from CC alias.
   */
  protected function parseLilaFromAlias(string $alias): ?string {
    if (preg_match('/\/books\/cc\/([^\/]+)\//', $alias, $matches)) {
      return $matches[1];
    }
    return null;
  }

  /**
   * Parse verse number from alias.
   */
  protected function parseVerseFromAlias(string $alias): int {
    if (preg_match('/\/(\d+)(?:-\d+)?$/', $alias, $matches)) {
      return (int) $matches[1];
    }
    
    return 0;
  }

  /**
   * Find chapter by number.
   */
  protected function findChapter(string $book_type, int $number): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    
    $chapter_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $book_type . '_chapter')
      ->condition('field_number', $number)
      ->range(0, 1)
      ->execute();
    
    if ($chapter_ids) {
      return $storage->load(reset($chapter_ids));
    }
    
    return null;
  }
  
  /**
   * Find SB chapter by song and chapter number.
   */
  protected function findSbChapter(int $song_num, int $chapter_num): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    
    // First find the song
    $song_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'sb_song')
      ->condition('field_number', $song_num)
      ->range(0, 1)
      ->execute();
    
    if (!$song_ids) {
      return null;
    }
    
    $song_id = reset($song_ids);
    
    // Then find the chapter belonging to this song
    $chapter_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'sb_chapter')
      ->condition('field_number', $chapter_num)
      ->condition('field_song_ref', $song_id)
      ->range(0, 1)
      ->execute();
    
    if ($chapter_ids) {
      return $storage->load(reset($chapter_ids));
    }
    
    return null;
  }
  
  /**
   * Find CC chapter by lila and chapter number.
   */
  protected function findCcChapter(string $lila_name, int $chapter_num): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    
    // Map lila names to numbers
    $lila_map = [
      'adi' => 1,
      'madhya' => 2,
      'antya' => 3,
    ];
    
    $lila_num = $lila_map[$lila_name] ?? null;
    if (!$lila_num) {
      return null;
    }
    
    // First find the lila
    $lila_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cc_lila')
      ->condition('field_number', $lila_num)
      ->range(0, 1)
      ->execute();
    
    if (!$lila_ids) {
      return null;
    }
    
    $lila_id = reset($lila_ids);
    
    // Then find the chapter belonging to this lila
    $chapter_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'cc_chapter')
      ->condition('field_number', $chapter_num)
      ->condition('field_lila_ref', $lila_id)
      ->range(0, 1)
      ->execute();
    
    if ($chapter_ids) {
      return $storage->load(reset($chapter_ids));
    }
    
    return null;
  }

  /**
   * Helper for translations.
   */
  protected function t($string, array $args = []) {
    return \Drupal::translation()->translate($string, $args);
  }

}
