<?php
namespace local_kaznu\service;

defined('MOODLE_INTERNAL') || die();

/**
 * DBA dissertation checks per Farabi IBS methodology (2026), incl. Appendix 11 checklist.
 */
final class thesis_dba_analyzer {

    public static function rules(): array {
        return [
            'page' => [
                'size' => 'A4',
                'margin_mm' => ['top' => 20, 'bottom' => 20, 'left' => 30, 'right' => 15],
            ],
            'body' => [
                'font' => ['Times New Roman', 'TimesNewRoman'],
                'size_pt' => 14,
                'line_spacing' => 1.0,
            ],
            'limits' => ['min_pages' => 70, 'max_pages' => 90],
            'program' => 'dba',
        ];
    }

    /** Required dissertation package (methodology §2.2–2.3 + Farabi process). */
    public static function document_package(): array {
        return [
            ['id' => 'title', 'title' => 'Титульный лист', 'in_thesis_file' => true],
            ['id' => 'abstract_ru', 'title' => 'Реферат (рус.)', 'in_thesis_file' => true],
            ['id' => 'abstract_kk', 'title' => 'Реферат (каз.)', 'in_thesis_file' => true],
            ['id' => 'abstract_en', 'title' => 'Реферат (англ.)', 'in_thesis_file' => true],
            ['id' => 'toc', 'title' => 'Содержание', 'in_thesis_file' => true],
            ['id' => 'abbrev', 'title' => 'Список сокращений', 'in_thesis_file' => true],
            ['id' => 'main', 'title' => 'Введение, 3 главы, заключение, источники, приложения', 'in_thesis_file' => true],
            ['id' => 'ai_decl', 'title' => 'Декларация / журнал ИИ (при использовании)', 'in_thesis_file' => true],
            ['id' => 'impl_act', 'title' => 'Акт внедрения / апробации (при наличии)', 'in_thesis_file' => false],
            ['id' => 'supervisor', 'title' => 'Отзывы научных консультантов', 'in_thesis_file' => false],
            ['id' => 'reviews', 'title' => 'Рецензии внешних экспертов', 'in_thesis_file' => false],
            ['id' => 'plagiarism', 'title' => 'Справка о проверке на заимствования', 'in_thesis_file' => false],
            ['id' => 'publications', 'title' => 'Публикации по теме (≥7 для DBA)', 'in_thesis_file' => false],
        ];
    }

    /** Human names for technical check ids (no lang keys required). */
    public static function check_id_labels(): array {
        return [
            'body.font' => 'Шрифт основного текста (Times New Roman)',
            'body.size' => 'Кегль шрифта 14 pt',
            'body.linespacing' => 'Межстрочный интервал (одинарный)',
            'page.margins' => 'Поля страницы',
            'file.type' => 'Формат файла',
            'content.title' => 'Тема работы',
            'content.volume' => 'Объём (страницы)',
            'structure.intro' => 'Раздел «Введение»',
            'structure.conclusion' => 'Раздел «Заключение»',
            'structure.sources' => 'Список использованных источников',
            'structure.toc' => 'Содержание',
            'structure.referat' => 'Реферат',
            'structure.chapters' => 'Три главы',
            'introduction.completeness' => 'Полнота введения',
            'content.citations' => 'Ссылки на источники в тексте',
            'ai.disclosure' => 'Раскрытие использования ИИ',
            'intro.actuality' => 'Введение: актуальность',
            'intro.literature' => 'Введение: степень изученности',
            'intro.problem' => 'Введение: проблема',
            'intro.goal' => 'Введение: цель',
            'intro.tasks' => 'Введение: задачи',
            'intro.object' => 'Введение: объект',
            'intro.subject' => 'Введение: предмет',
            'intro.methods' => 'Введение: методология',
            'intro.novelty' => 'Введение: новизна и значимость',
            'intro.defence' => 'Введение: положения на защиту',
            'intro.approbation' => 'Введение: апробация и публикации',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     * @return array<int,array<string,mixed>>
     */
    public static function apply_check_labels(array $checks): array {
        foreach ($checks as &$row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $row['label'] = self::label_for_check_id($id);
            }
        }
        unset($row);
        return $checks;
    }

    public static function label_for_check_id(string $id): string {
        $map = self::check_id_labels();
        if (isset($map[$id])) {
            return $map[$id];
        }
        foreach (self::appendix11_labels() as $row) {
            if ($row['id'] === $id) {
                return $row['label'];
            }
        }
        return str_replace(['_', '.'], [' ', ' — '], $id);
    }

    public static function label_for_appendix_id(string $id): string {
        foreach (self::appendix11_labels() as $row) {
            if ($row['id'] === $id) {
                return $row['label'];
            }
        }
        return self::label_for_check_id($id);
    }

    public static function status_label_ru(string $status): string {
        return match (mb_strtolower($status)) {
            'ok' => 'Выполнено',
            'fail', 'failed' => 'Есть замечания',
            'warn', 'warning' => 'Нужна проверка',
            default => 'Не удалось проверить автоматически',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $checklist
     * @return array<int,array<string,mixed>>
     */
    public static function enrich_checklist(array $checklist): array {
        foreach ($checklist as &$row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $row['label'] = self::label_for_appendix_id($id);
            }
            $st = (string) ($row['status'] ?? 'unknown');
            $row['status_label'] = self::status_label_ru($st);
        }
        unset($row);
        return $checklist;
    }

    /** Sections for “Что проверяет ИИ” modal. */
    public static function ai_info_sections(): array {
        return [
            [
                'title' => 'Оформление (из DOCX)',
                'items' => [
                    'Формат A4, шрифт Times New Roman 14 pt, одинарный интервал.',
                    'Поля: слева 30 мм, справа 15 мм, сверху и снизу 20 мм.',
                    'Рекомендуемый объём DBA: 70–90 страниц основного текста.',
                ],
            ],
            [
                'title' => 'Структура диссертации',
                'items' => [
                    'Введение, три главы, заключение, список источников.',
                    'Реферат, содержание, наличие основных разделов.',
                ],
            ],
            [
                'title' => 'Содержание введения',
                'items' => [
                    'Актуальность, проблема, цель, задачи, объект и предмет.',
                    'Методология, новизна, положения на защиту, апробация.',
                ],
            ],
            [
                'title' => 'ИИ и академическая честность',
                'items' => [
                    'Упоминание или декларация об использовании ИИ.',
                    'Наличие библиографических ссылок [n] в тексте.',
                ],
            ],
            [
                'title' => 'Чек-лист самопроверки (приложение 11)',
                'items' => array_map(
                    fn($r) => $r['label'],
                    self::appendix11_labels()
                ),
            ],
            [
                'title' => 'Что сервис не заменяет',
                'items' => [
                    'Официальную проверку на заимствования (НЦГНТЭ).',
                    'Решение диссертационного совета и юридический допуск к защите.',
                    'Проверку отдельных файлов (отзывы, рецензии) вне загруженного DOCX.',
                ],
            ],
        ];
    }

  /** @return array<int,array{id:string,label:string}> */
    public static function appendix11_labels(): array {
        return [
            ['id' => 'chk_goal_tasks', 'label' => 'Тема, цель, задачи, объект и предмет согласованы'],
            ['id' => 'chk_tasks_structure', 'label' => 'Каждая задача отражена в структуре'],
            ['id' => 'chk_ch1', 'label' => 'Глава 1: теоретико-методологическая основа'],
            ['id' => 'chk_ch2', 'label' => 'Глава 2: диагностика объекта и проблема'],
            ['id' => 'chk_ch3', 'label' => 'Глава 3: проектное решение и эффект'],
            ['id' => 'chk_ch_conclusions', 'label' => 'Выводы по главам конкретны'],
            ['id' => 'chk_conclusion', 'label' => 'Заключение: цель и задачи'],
            ['id' => 'chk_tables_figures', 'label' => 'Таблицы/рисунки: номера и источники'],
            ['id' => 'chk_bibliography', 'label' => 'Согласованность ссылок и списка источников'],
            ['id' => 'chk_citations', 'label' => 'Цитаты, статистика, нормативные акты проверены'],
            ['id' => 'chk_appendix_order', 'label' => 'Приложения упомянуты и по порядку'],
            ['id' => 'chk_implementation', 'label' => 'Акт внедрения/апробации (при необходимости)'],
            ['id' => 'chk_ai_disclosure', 'label' => 'Раскрытие использования ИИ'],
            ['id' => 'chk_ai_privacy', 'label' => 'Нет утечки конфиденциальных данных в ИИ'],
            ['id' => 'chk_integrity', 'label' => 'Академическая честность / заимствования'],
            ['id' => 'chk_deadline', 'label' => 'Срок представления консультантам'],
        ];
    }

    /**
     * @param array $parsed from thesis_service::parse_document
     * @param array $rules
     * @return array<int,array<string,mixed>>
     */
    public static function formatting_checks(array $parsed, array $rules): array {
        $checks = [];
        $body = $rules['body'] ?? [];
        $page = $rules['page']['margin_mm'] ?? [];
        $limits = $rules['limits'] ?? [];

        $allowedfonts = array_map('mb_strtolower', $body['font'] ?? ['times new roman']);
        $fonts = array_map('mb_strtolower', $parsed['fonts'] ?? []);
        $fontok = $parsed['ext'] !== 'docx' ? false
            : (empty($fonts) || (bool) array_intersect($fonts, $allowedfonts));
        $checks[] = [
            'id' => 'body.font',
            'label' => get_string('thesis_check_font', 'local_kaznu'),
            'ok' => $fontok,
            'expected' => 'Times New Roman, 14 pt',
            'found' => $fonts ? implode(', ', array_slice($parsed['fonts'], 0, 5))
                : ($parsed['body_font_pt'] ?? '—'),
            'group' => 'format',
        ];

        $sizept = $parsed['body_font_pt'] ?? null;
        $expectpt = (int) ($body['size_pt'] ?? 14);
        $sizeok = $sizept === null ? ($parsed['ext'] !== 'docx') : (abs($sizept - $expectpt) <= 1);
        $checks[] = [
            'id' => 'body.size',
            'label' => get_string('thesis_check_fontsize', 'local_kaznu'),
            'ok' => $sizeok,
            'expected' => $expectpt . ' pt',
            'found' => $sizept !== null ? ($sizept . ' pt') : '—',
            'group' => 'format',
        ];

        $linesp = $parsed['line_spacing'] ?? null;
        $expectls = (float) ($body['line_spacing'] ?? 1.0);
        $lsok = $linesp === null ? ($parsed['ext'] !== 'docx') : (abs($linesp - $expectls) <= 0.15);
        $checks[] = [
            'id' => 'body.linespacing',
            'label' => get_string('thesis_check_linespacing', 'local_kaznu'),
            'ok' => $lsok,
            'expected' => (string) $expectls . ' (одинарный)',
            'found' => $linesp !== null ? (string) $linesp : '—',
            'group' => 'format',
        ];

        $marginok = false;
        $foundmargin = '—';
        if (!empty($parsed['margins']['left'])) {
            $foundmargin = sprintf(
                'L%s / R%s / T%s / B%s mm',
                $parsed['margins']['left'],
                $parsed['margins']['right'],
                $parsed['margins']['top'],
                $parsed['margins']['bottom']
            );
            $marginok = ((float) $parsed['margins']['left'] >= ($page['left'] ?? 28) - 2)
                && ((float) $parsed['margins']['right'] >= ($page['right'] ?? 13) - 2)
                && ((float) $parsed['margins']['top'] >= ($page['top'] ?? 18) - 2)
                && ((float) $parsed['margins']['bottom'] >= ($page['bottom'] ?? 18) - 2);
        }
        $checks[] = [
            'id' => 'page.margins',
            'label' => get_string('thesis_check_margins', 'local_kaznu'),
            'ok' => $marginok || $parsed['ext'] !== 'docx',
            'expected' => sprintf('L%d R%d T%d B%d mm', $page['left'] ?? 30, $page['right'] ?? 15, $page['top'] ?? 20, $page['bottom'] ?? 20),
            'found' => $foundmargin,
            'group' => 'format',
        ];

        $pages = (int) ($parsed['pages_est'] ?? 0);
        $minp = (int) ($limits['min_pages'] ?? 70);
        $maxp = (int) ($limits['max_pages'] ?? 90);
        $volok = $pages >= $minp && $pages <= $maxp;
        $checks[] = [
            'id' => 'content.volume',
            'label' => get_string('thesis_check_volume', 'local_kaznu'),
            'ok' => $volok,
            'expected' => "{$minp}–{$maxp} стр. основного текста",
            'found' => $pages . ' стр. (≈), ' . ($parsed['word_count'] ?? 0) . ' слов',
            'group' => 'format',
        ];

        return $checks;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function structure_checks(string $text): array {
        $t = mb_strtolower($text);
        $checks = [];

        $sections = [
            ['id' => 'structure.intro', 'label' => get_string('thesis_check_intro', 'local_kaznu'), 'patterns' => ['введение']],
            ['id' => 'structure.conclusion', 'label' => get_string('thesis_check_conclusion', 'local_kaznu'), 'patterns' => ['заключение']],
            ['id' => 'structure.sources', 'label' => get_string('thesis_check_sources', 'local_kaznu'), 'patterns' => [
                'список использованных источников', 'список литературы', 'библиографический список',
            ]],
            ['id' => 'structure.toc', 'label' => get_string('thesis_check_toc', 'local_kaznu'), 'patterns' => ['содержание', 'оглавление']],
            ['id' => 'structure.referat', 'label' => get_string('thesis_check_abstract', 'local_kaznu'), 'patterns' => ['реферат']],
        ];
        foreach ($sections as $s) {
            $ok = false;
            foreach ($s['patterns'] as $p) {
                if (mb_strpos($t, $p) !== false) {
                    $ok = true;
                    break;
                }
            }
            $checks[] = [
                'id' => $s['id'],
                'label' => $s['label'],
                'ok' => $ok,
                'expected' => 'раздел присутствует',
                'found' => $ok ? 'найдено' : 'не обнаружено',
                'group' => 'structure',
            ];
        }

        $chapters = 0;
        if (preg_match('/глава\s*1/u', $t) || preg_match('/\n1[\.\s].*теорет/u', $t)) {
            $chapters++;
        }
        if (preg_match('/глава\s*2/u', $t) || preg_match('/\n2[\.\s].*диагност/u', $t)) {
            $chapters++;
        }
        if (preg_match('/глава\s*3/u', $t) || preg_match('/\n3[\.\s].*проект/u', $t)) {
            $chapters++;
        }
        $checks[] = [
            'id' => 'structure.chapters',
            'label' => get_string('thesis_check_chapters', 'local_kaznu'),
            'ok' => $chapters >= 3,
            'expected' => '3 главы DBA',
            'found' => "обнаружено глав: {$chapters}",
            'group' => 'structure',
        ];

        $introfields = [
            ['актальност', 'intro.actuality'],
            ['степень изученности', 'intro.literature'],
            ['проблема исследования', 'intro.problem'],
            ['цель исследования', 'intro.goal'],
            ['задачи исследования', 'intro.tasks'],
            ['объект исследования', 'intro.object'],
            ['предмет исследования', 'intro.subject'],
            ['методолог', 'intro.methods'],
            ['новизн', 'intro.novelty'],
            ['положения', 'intro.defence'],
            ['апробац', 'intro.approbation'],
        ];
        $idlabels = self::check_id_labels();
        $found = 0;
        foreach ($introfields as [$needle, $id]) {
            $ok = mb_strpos($t, $needle) !== false;
            if ($ok) {
                $found++;
            }
            $checks[] = [
                'id' => $id,
                'label' => $idlabels[$id] ?? ('Введение: ' . $needle),
                'ok' => $ok,
                'expected' => 'должно быть во введении',
                'found' => $ok ? 'найдено' : 'не найдено',
                'group' => 'introduction',
            ];
        }
        $checks[] = [
            'id' => 'introduction.completeness',
            'label' => get_string('thesis_check_intro_score', 'local_kaznu'),
            'ok' => $found >= 8,
            'expected' => '≥ 8 элементов введения',
            'found' => "{$found}/" . count($introfields),
            'group' => 'introduction',
        ];

        $refs = preg_match_all('/\[\d+\]/u', $text, $m) ? count($m[0]) : 0;
        $checks[] = [
            'id' => 'content.citations',
            'label' => get_string('thesis_check_citations', 'local_kaznu'),
            'ok' => $refs >= 5,
            'expected' => 'ссылки [n] в тексте',
            'found' => (string) $refs,
            'group' => 'content',
        ];

        $aidisclosed = (mb_strpos($t, 'искусственн') !== false && mb_strpos($t, 'интеллект') !== false)
            || mb_strpos($t, 'декларац') !== false
            || mb_strpos($t, 'технологии искусственного интеллекта не использовались') !== false;
        $checks[] = [
            'id' => 'ai.disclosure',
            'label' => get_string('thesis_check_ai_disclosure', 'local_kaznu'),
            'ok' => $aidisclosed,
            'expected' => 'раскрытие ИИ или явный отказ',
            'found' => $aidisclosed ? 'упоминание найдено' : 'не найдено',
            'group' => 'ai',
        ];

        return $checks;
    }

    /**
     * OpenAI structured review for Appendix 11 + recommendations.
     *
     * @return array{summary:string,checklist:array,hints:array}|null
     */
    public static function llm_appendix_review(string $apikey, string $title, string $text, array $localchecks): ?array {
        $sample = \core_text::substr($text, 0, 12000);
        $failed = [];
        foreach ($localchecks as $c) {
            if (empty($c['ok'])) {
                $failed[] = ($c['label'] ?? $c['id']) . ': ' . ($c['found'] ?? '');
            }
        }
        $items = self::appendix11_labels();
        $list = '';
        foreach ($items as $it) {
            $list .= "- {$it['id']}: {$it['label']}\n";
        }

        $system = 'Ты методист Farabi IBS (DBA KazNU). Проверяешь диссертацию по методическим указаниям 2026. '
            . 'Оформление: A4, Times New Roman 14, одинарный интервал, поля 30/15/20/20 мм, 70–90 стр. '
            . 'Не заменяешь официальный антиплагиат и решение диссовета. Отвечай только JSON.';

        $user = "Тема: {$title}\n\nУже найденные проблемы парсера:\n"
            . implode("\n", $failed ?: ['критичных нет']) . "\n\n"
            . "Чек-лист (приложение 11), для каждого id верни status: ok|warn|fail|unknown и comment (1 предложение):\n"
            . $list . "\n\nФрагмент диссертации:\n{$sample}\n\n"
            . 'JSON: {"summary":"2-4 предложения","checklist":[{"id":"","status":"","comment":""}],'
            . '"formatting_notes":["…"],"recommendations":["…"]}';

        $model = trim((string) (get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini'));
        $payload = json_encode([
            'model' => $model,
            'temperature' => 0.15,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ], JSON_UNESCAPED_UNICODE);

        if (!function_exists('curl_init')) {
            require_once($GLOBALS['CFG']->libdir . '/filelib.php');
        }
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apikey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || !is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            return null;
        }
        return [
            'summary' => (string) ($parsed['summary'] ?? ''),
            'checklist' => is_array($parsed['checklist'] ?? null) ? $parsed['checklist'] : [],
            'formatting_notes' => is_array($parsed['formatting_notes'] ?? null) ? $parsed['formatting_notes'] : [],
            'recommendations' => is_array($parsed['recommendations'] ?? null) ? $parsed['recommendations'] : [],
        ];
    }
}