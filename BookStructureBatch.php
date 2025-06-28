<?php

namespace Drupal\shloka\Batch;

/**
 * Batch operations for book structure management.
 */
class BookStructureBatch {

  /**
   * Batch operation to create chapters.
   */
  public static function createChapters($book_type, $config, &$context) {
    $manager = \Drupal::service('shloka.book_structure_manager');
    
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = 1;
    }
    
    try {
      $manager->createChapters($book_type, $config);
      $context['sandbox']['progress'] = 1;
      $context['message'] = t('Создание глав для @type завершено.', ['@type' => $book_type]);
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('shloka')->error($e->getMessage());
    }
    
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch operation to create songs.
   */
  public static function createSongs(&$context) {
    $manager = \Drupal::service('shloka.book_structure_manager');
    
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = 1;
    }
    
    try {
      $manager->createSongs();
      $context['sandbox']['progress'] = 1;
      $context['message'] = t('Создание песен ШБ завершено.');
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('shloka')->error($e->getMessage());
    }
    
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch operation to create lilas.
   */
  public static function createLilas(&$context) {
    $manager = \Drupal::service('shloka.book_structure_manager');
    
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = 1;
    }
    
    try {
      $manager->createLilas();
      $context['sandbox']['progress'] = 1;
      $context['message'] = t('Создание лил ЧЧ завершено.');
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('shloka')->error($e->getMessage());
    }
    
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Batch operation to assign verses to chapters.
   */
  public static function assignVerses($book_type, &$context) {
    $manager = \Drupal::service('shloka.book_structure_manager');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    
    if (!isset($context['sandbox']['progress'])) {
      // Get total verses to process
      $verse_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $book_type)
        ->execute();
      
      $context['sandbox']['verse_ids'] = array_values($verse_ids);
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($verse_ids);
    }
    
    // Process verses in batches of 50
    $batch_size = 50;
    $current_batch = array_slice(
      $context['sandbox']['verse_ids'],
      $context['sandbox']['progress'],
      $batch_size
    );
    
    if (empty($current_batch)) {
      $context['finished'] = 1;
      return;
    }
    
    try {
      // Process current batch
      foreach ($current_batch as $verse_id) {
        $verse = $storage->load($verse_id);
        if ($verse) {
          // Get alias directly from database
          $database = \Drupal::database();
          $alias = $database->select('path_alias', 'pa')
            ->fields('pa', ['alias'])
            ->condition('path', '/node/' . $verse_id)
            ->condition('status', 1)
            ->execute()
            ->fetchField();
          
          if (!$alias) {
            \Drupal::logger('shloka')->warning('No alias found for verse @id', ['@id' => $verse_id]);
            continue;
          }
          
          $chapter_num = self::parseChapterFromAlias($alias, $book_type);
          
          if ($chapter_num) {
            $chapter = self::findChapter($book_type, $chapter_num);
            
            if ($chapter) {
              $verse_num = self::parseVerseFromAlias($alias);
              
              // Remove existing book entry directly from database
              $database = \Drupal::database();
              try {
                $database->delete('book')
                  ->condition('nid', $verse->id())
                  ->execute();
              } catch (\Exception $e) {
                // Log but continue - record might not exist
                \Drupal::logger('shloka')->debug('No existing book entry for verse @id', ['@id' => $verse->id()]);
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
              } catch (\Exception $e) {
                \Drupal::logger('shloka')->warning('Failed to assign verse @id: @error', [
                  '@id' => $verse->id(),
                  '@error' => $e->getMessage(),
                ]);
              }
            }
          }
        }
        
        $context['sandbox']['progress']++;
      }
      
      $context['message'] = t('Обработано @current из @total стихов.', [
        '@current' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['max'],
      ]);
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('shloka')->error($e->getMessage());
    }
    
    $context['finished'] = $context['sandbox']['max'] > 0 
      ? $context['sandbox']['progress'] / $context['sandbox']['max'] 
      : 1;
  }

  /**
   * Batch operation to delete structure.
   */
  public static function deleteStructure($book_type, &$context) {
    $manager = \Drupal::service('shloka.book_structure_manager');
    
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = 1;
    }
    
    try {
      $manager->deleteStructure($book_type);
      $context['sandbox']['progress'] = 1;
      $context['message'] = t('Удаление структуры @type завершено.', ['@type' => $book_type]);
    }
    catch (\Exception $e) {
      $context['results']['errors'][] = $e->getMessage();
      \Drupal::logger('shloka')->error($e->getMessage());
    }
    
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Helper: Parse chapter from alias.
   */
  private static function parseChapterFromAlias($alias, $book_type) {
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
   * Helper: Parse verse from alias.
   */
  private static function parseVerseFromAlias($alias) {
    if (preg_match('/\/(\d+)(?:-\d+)?$/', $alias, $matches)) {
      return (int) $matches[1];
    }
    
    return 0;
  }

  /**
   * Helper: Find chapter by number.
   */
  private static function findChapter($book_type, $number) {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    
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

}
