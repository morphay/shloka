<?php

namespace Drupal\shloka\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Batch\BatchBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\shloka\Service\BookStructureManager;

/**
 * Admin form for managing book structures.
 */
class ShlokaAdminForm extends FormBase {

  /**
   * The book structure manager.
   *
   * @var \Drupal\shloka\Service\BookStructureManager
   */
  protected $bookStructureManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->bookStructureManager = $container->get('shloka.book_structure_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'shloka_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Управление структурой священных книг') . '</p>',
    ];

    // Bhagavad-gita section
    $form['bg'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Бхагавад-гита'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['bg']['create_bg_chapters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Создать главы БГ'),
      '#submit' => ['::createBgChapters'],
    ];

    $form['bg']['assign_bg_verses'] = [
      '#type' => 'submit',
      '#value' => $this->t('Подчинить стихи БГ главам'),
      '#submit' => ['::assignBgVerses'],
    ];

    $form['bg']['delete_bg_structure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Удалить структуру БГ'),
      '#submit' => ['::deleteBgStructure'],
      '#attributes' => [
        'class' => ['button--danger'],
      ],
    ];

    // Srimad-Bhagavatam section
    $form['sb'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Шримад-Бхагаватам'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['sb']['create_sb_songs'] = [
      '#type' => 'submit',
      '#value' => $this->t('Создать песни ШБ'),
      '#submit' => ['::createSbSongs'],
    ];

    $form['sb']['assign_sb_chapters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Подчинить главы ШБ песням'),
      '#submit' => ['::assignSbChapters'],
    ];

    $form['sb']['assign_sb_verses'] = [
      '#type' => 'submit',
      '#value' => $this->t('Подчинить стихи ШБ главам'),
      '#submit' => ['::assignSbVerses'],
    ];

    $form['sb']['delete_sb_structure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Удалить структуру ШБ'),
      '#submit' => ['::deleteSbStructure'],
      '#attributes' => [
        'class' => ['button--danger'],
      ],
    ];

    // Chaitanya-Charitamrita section
    $form['cc'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Чайтанья-Чаритамрита'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['cc']['create_cc_lilas'] = [
      '#type' => 'submit',
      '#value' => $this->t('Создать лилы ЧЧ'),
      '#submit' => ['::createCcLilas'],
    ];

    $form['cc']['assign_cc_chapters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Подчинить главы ЧЧ лилам'),
      '#submit' => ['::assignCcChapters'],
    ];

    $form['cc']['assign_cc_verses'] = [
      '#type' => 'submit',
      '#value' => $this->t('Подчинить стихи ЧЧ главам'),
      '#submit' => ['::assignCcVerses'],
    ];

    $form['cc']['delete_cc_structure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Удалить структуру ЧЧ'),
      '#submit' => ['::deleteCcStructure'],
      '#attributes' => [
        'class' => ['button--danger'],
      ],
    ];

    // Menu sync
    $form['menu'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Меню'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['menu']['sync_menu'] = [
      '#type' => 'submit',
      '#value' => $this->t('Синхронизировать меню navigaciya'),
      '#submit' => ['::syncMenu'],
    ];

    // Configuration
    $form['config'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Конфигурация'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $config = $this->config('shloka.settings');
    
    $form['config']['bg_main_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('ID главной страницы БГ'),
      '#default_value' => $config->get('structure.bg.main_book_nid'),
      '#min' => 1,
    ];

    $form['config']['sb_main_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('ID главной страницы ШБ'),
      '#default_value' => $config->get('structure.sb.main_book_nid'),
      '#min' => 1,
    ];

    $form['config']['cc_main_nid'] = [
      '#type' => 'number',
      '#title' => $this->t('ID главной страницы ЧЧ'),
      '#default_value' => $config->get('structure.cc.main_book_nid'),
      '#min' => 1,
    ];

    $form['config']['save_config'] = [
      '#type' => 'submit',
      '#value' => $this->t('Сохранить конфигурацию'),
      '#submit' => ['::saveConfig'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Main submit is handled by individual button handlers
  }

  /**
   * Create BG chapters.
   */
  public function createBgChapters(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Создание глав Бхагавад-гиты'))
      ->setInitMessage($this->t('Начинаем создание глав...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при создании глав.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'createChapters'], ['bg', []]);
    
    batch_set($batch->toArray());
  }

  /**
   * Assign BG verses to chapters.
   */
  public function assignBgVerses(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Подчинение стихов главам БГ'))
      ->setInitMessage($this->t('Начинаем подчинение стихов...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при подчинении стихов.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'assignVerses'], ['bg']);
    
    batch_set($batch->toArray());
  }

  /**
   * Delete BG structure.
   */
  public function deleteBgStructure(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Удаление структуры БГ'))
      ->setInitMessage($this->t('Начинаем удаление...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при удалении.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'deleteStructure'], ['bg']);
    
    batch_set($batch->toArray());
  }

  /**
   * Create SB songs.
   */
  public function createSbSongs(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Создание песен Шримад-Бхагаватам'))
      ->setInitMessage($this->t('Начинаем создание песен...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при создании песен.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'createSongs'], []);
    
    batch_set($batch->toArray());
  }

  /**
   * Assign SB chapters to songs.
   */
  public function assignSbChapters(array &$form, FormStateInterface $form_state) {
    // This is handled automatically when creating songs
    $this->messenger()->addStatus($this->t('Главы ШБ уже подчинены песням при их создании.'));
  }

  /**
   * Assign SB verses to chapters.
   */
  public function assignSbVerses(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Подчинение стихов главам ШБ'))
      ->setInitMessage($this->t('Начинаем подчинение стихов...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при подчинении стихов.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'assignVerses'], ['sb']);
    
    batch_set($batch->toArray());
  }

  /**
   * Delete SB structure.
   */
  public function deleteSbStructure(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Удаление структуры ШБ'))
      ->setInitMessage($this->t('Начинаем удаление...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при удалении.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'deleteStructure'], ['sb']);
    
    batch_set($batch->toArray());
  }

  /**
   * Create CC lilas.
   */
  public function createCcLilas(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Создание лил Чайтанья-Чаритамриты'))
      ->setInitMessage($this->t('Начинаем создание лил...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при создании лил.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'createLilas'], []);
    
    batch_set($batch->toArray());
  }

  /**
   * Assign CC chapters to lilas.
   */
  public function assignCcChapters(array &$form, FormStateInterface $form_state) {
    // This is handled automatically when creating lilas
    $this->messenger()->addStatus($this->t('Главы ЧЧ уже подчинены лилам при их создании.'));
  }

  /**
   * Assign CC verses to chapters.
   */
  public function assignCcVerses(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Подчинение стихов главам ЧЧ'))
      ->setInitMessage($this->t('Начинаем подчинение стихов...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при подчинении стихов.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'assignVerses'], ['cc']);
    
    batch_set($batch->toArray());
  }

  /**
   * Delete CC structure.
   */
  public function deleteCcStructure(array &$form, FormStateInterface $form_state) {
    $batch = new BatchBuilder();
    $batch->setTitle($this->t('Удаление структуры ЧЧ'))
      ->setInitMessage($this->t('Начинаем удаление...'))
      ->setProgressMessage($this->t('Обработано @current из @total.'))
      ->setErrorMessage($this->t('Произошла ошибка при удалении.'));

    $batch->addOperation(['\Drupal\shloka\Batch\BookStructureBatch', 'deleteStructure'], ['cc']);
    
    batch_set($batch->toArray());
  }

  /**
   * Sync menu.
   */
  public function syncMenu(array &$form, FormStateInterface $form_state) {
    try {
      $this->bookStructureManager->syncWithMenutree();
      $this->messenger()->addStatus($this->t('Меню успешно синхронизировано.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Ошибка синхронизации меню: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Save configuration.
   */
  public function saveConfig(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('shloka.settings');
    
    $config->set('structure.bg.main_book_nid', $form_state->getValue('bg_main_nid'));
    $config->set('structure.sb.main_book_nid', $form_state->getValue('sb_main_nid'));
    $config->set('structure.cc.main_book_nid', $form_state->getValue('cc_main_nid'));
    
    $config->save();
    
    $this->messenger()->addStatus($this->t('Конфигурация сохранена.'));
  }

}
