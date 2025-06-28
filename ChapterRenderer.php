<?php

namespace Drupal\shloka\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;

/**
 * Service for rendering chapter content.
 */
class ChapterRenderer {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
  }

  /**
   * Get all verses for a chapter.
   */
  public function getChapterVerses(NodeInterface $chapter): array {
    $storage = $this->entityTypeManager->getStorage('node');
    
    // Determine book type from chapter type
    $book_type = str_replace('_chapter', '', $chapter->bundle());
    
    // Get all verses of this type
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $book_type)
      ->condition('status', 1);
    
    $verse_ids = $query->execute();
    
    if (empty($verse_ids)) {
      return [];
    }
    
    $verses = $storage->loadMultiple($verse_ids);
    $chapter_verses = [];
    
    // Filter verses that belong to this chapter
    foreach ($verses as $verse) {
      // Check if verse has book info and belongs to this chapter
      if (!empty($verse->book['pid']) && $verse->book['pid'] == $chapter->id()) {
        // Get weight for sorting
        $weight = isset($verse->book['weight']) ? $verse->book['weight'] : 0;
        $chapter_verses[$weight] = $verse;
      }
    }
    
    // Sort by weight
    ksort($chapter_verses);
    
    return array_values($chapter_verses);
  }

  /**
   * Render verses for display.
   */
  public function renderVerses(array $verses, string $langcode): array {
    $output = [];
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    
    foreach ($verses as $verse) {
      // Check if translation exists
      if ($verse->hasTranslation($langcode)) {
        $verse = $verse->getTranslation($langcode);
      }
      
      // Get verse alias to check for compound verses
      $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $verse->id());
      $verse_range = $this->parseVerseRange($alias);
      
      // For compound verses, we need to load all verses in the range
      if (count($verse_range) > 1) {
        $compound_verses = $this->loadVerseRange($verse, $verse_range);
        
        foreach ($compound_verses as $compound_verse) {
          if ($compound_verse->hasTranslation($langcode)) {
            $compound_verse = $compound_verse->getTranslation($langcode);
          }
          
          $verse_output = $this->renderSingleVerse($compound_verse);
          if ($verse_output) {
            $output[] = [
              '#markup' => '<div class="verse-content">' . $verse_output . '</div><br><br>',
            ];
          }
        }
      }
      else {
        // Single verse
        $verse_output = $this->renderSingleVerse($verse);
        if ($verse_output) {
          $output[] = [
            '#markup' => '<div class="verse-content">' . $verse_output . '</div>',
          ];
        }
      }
    }
    
    return $output;
  }
  
  /**
   * Render a single verse with title and translation.
   */
  private function renderSingleVerse(NodeInterface $verse): string {
    $output = '';
    
    // Get verse URL
    $url = $verse->toUrl()->toString();
    
    // Get and clean title
    if ($verse->hasField('field_title') && !$verse->get('field_title')->isEmpty()) {
      $title_field = $verse->get('field_title');
      $title_value = $title_field->value;
      
      // Strip HTML tags to get clean title
      $title_text = strip_tags($title_value);
      $title_text = trim($title_text);
      
      if (!empty($title_text)) {
        $output .= '<strong><a href="' . $url . '" class="verse-title-link">' . $title_text . ':</a></strong> ';
      }
    }
    
    // Get and clean translation
    if ($verse->hasField('field_translate') && !$verse->get('field_translate')->isEmpty()) {
      $translate_field = $verse->get('field_translate');
      $translate_value = $translate_field->value;
      
      // Remove "TRANSLATE", "TRANSLATION" and "ПЕРЕВОД" words from the text
      $translate_value = preg_replace('/\*?\*?\s*(TRANSLAT(E|ION)|ПЕРЕВОД)\s*\*?\*?/i', '', $translate_value);
      
      // Strip all HTML tags first
      $translate_value = strip_tags($translate_value);
      
      // Clean up the text - remove excessive asterisks and clean formatting
      $translate_value = preg_replace('/\*\*+/', '', $translate_value);
      $translate_value = preg_replace('/\s+/', ' ', $translate_value);
      $translate_value = trim($translate_value);
      
      $output .= $translate_value;
    }
    
    return $output;
  }

  /**
   * Parse verse range from alias.
   */
  private function parseVerseRange(string $alias): array {
    // Handle compound verses like /books/bg/1/5-8
    if (preg_match('/\/(\d+)-(\d+)$/', $alias, $matches)) {
      return range((int) $matches[1], (int) $matches[2]);
    }
    
    // Single verse
    if (preg_match('/\/(\d+)$/', $alias, $matches)) {
      return [(int) $matches[1]];
    }
    
    return [];
  }

  /**
   * Load all verses in a range.
   */
  private function loadVerseRange(NodeInterface $base_verse, array $range): array {
    if (count($range) <= 1) {
      return [$base_verse];
    }
    
    $storage = $this->entityTypeManager->getStorage('node');
    $book_type = $base_verse->bundle();
    
    // Get the base path from alias
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $base_verse->id());
    $base_path = preg_replace('/\/\d+(-\d+)?$/', '', $alias);
    
    $verses = [];
    
    foreach ($range as $verse_num) {
      $verse_alias = $base_path . '/' . $verse_num;
      $path = \Drupal::service('path_alias.manager')->getPathByAlias($verse_alias);
      
      if (preg_match('/node\/(\d+)/', $path, $matches)) {
        $verse = $storage->load($matches[1]);
        if ($verse && $verse->bundle() === $book_type) {
          $verses[] = $verse;
        }
      }
    }
    
    return $verses ?: [$base_verse];
  }

  /**
   * Extract child node IDs from book tree.
   */
  private function extractChildNids(array $tree, int $parent_nid): array {
    $nids = [];
    
    foreach ($tree as $item) {
      if ($item['link']['nid'] == $parent_nid && !empty($item['below'])) {
        foreach ($item['below'] as $child) {
          if (!empty($child['link']['nid'])) {
            $node = $this->entityTypeManager->getStorage('node')->load($child['link']['nid']);
            
            // Only include verses (not sub-chapters)
            if ($node && !in_array($node->bundle(), ['bg_chapter', 'sb_chapter', 'cc_chapter', 'sb_song', 'cc_lila'])) {
              $nids[] = $child['link']['nid'];
            }
            
            // Recursively check for more children
            if (!empty($child['below'])) {
              $nids = array_merge($nids, $this->extractChildNids([$child], $child['link']['nid']));
            }
          }
        }
        break;
      }
      
      // Continue searching in subtree
      if (!empty($item['below'])) {
        $found = $this->extractChildNids($item['below'], $parent_nid);
        if ($found) {
          $nids = array_merge($nids, $found);
        }
      }
    }
    
    return $nids;
  }

}
