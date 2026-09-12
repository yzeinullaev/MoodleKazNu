#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Generate GPH TZ and ACT templates (DOCX + PDF) for Farabi School."""

from __future__ import annotations

import os
import sys
from datetime import date
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

BASE = Path(__file__).resolve().parent
FONT_PATH = Path(r"C:\Windows\Fonts\arial.ttf")
FONT_BOLD_PATH = Path(r"C:\Windows\Fonts\arialbd.ttf")


def register_pdf_fonts() -> tuple[str, str]:
    regular, bold = "Arial", "Arial-Bold"
    if FONT_PATH.exists():
        pdfmetrics.registerFont(TTFont(regular, str(FONT_PATH)))
    if FONT_BOLD_PATH.exists():
        pdfmetrics.registerFont(TTFont(bold, str(FONT_BOLD_PATH)))
    return regular, bold


def set_doc_defaults(doc: Document) -> None:
    section = doc.sections[0]
    section.page_height = Cm(29.7)
    section.page_width = Cm(21.0)
    section.top_margin = Cm(2.0)
    section.bottom_margin = Cm(2.0)
    section.left_margin = Cm(2.5)
    section.right_margin = Cm(1.5)
    normal = doc.styles["Normal"]
    normal.font.name = "Times New Roman"
    normal.font.size = Pt(12)
    normal._element.rPr.rFonts.set(qn("w:eastAsia"), "Times New Roman")


def add_title(doc: Document, text: str) -> None:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run(text)
    r.bold = True
    r.font.size = Pt(14)
    r.font.name = "Times New Roman"


def add_h(doc: Document, text: str, level: int = 1) -> None:
    h = doc.add_heading(text, level=level)
    for r in h.runs:
        r.font.name = "Times New Roman"
        r.font.color.rgb = RGBColor(0, 0, 0)


def add_p(doc: Document, text: str, bold: bool = False) -> None:
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(6)
    p.paragraph_format.first_line_indent = Cm(1.0)
    r = p.add_run(text)
    r.bold = bold
    r.font.name = "Times New Roman"
    r.font.size = Pt(12)


def add_bullets(doc: Document, items: list[str]) -> None:
    for item in items:
        p = doc.add_paragraph(item, style="List Bullet")
        for r in p.runs:
            r.font.name = "Times New Roman"
            r.font.size = Pt(12)


def add_table(doc: Document, headers: list[str], rows: list[list[str]]) -> None:
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    hdr = table.rows[0].cells
    for i, h in enumerate(headers):
        hdr[i].text = h
        for p in hdr[i].paragraphs:
            for r in p.runs:
                r.bold = True
                r.font.name = "Times New Roman"
                r.font.size = Pt(10)
    for ri, row in enumerate(rows, start=1):
        for ci, val in enumerate(row):
            cell = table.rows[ri].cells[ci]
            cell.text = val
            for p in cell.paragraphs:
                for r in p.runs:
                    r.font.name = "Times New Roman"
                    r.font.size = Pt(10)
    doc.add_paragraph()


# ---------------------------------------------------------------------------
# Content: 6-month stages
# ---------------------------------------------------------------------------

STAGES = [
    {
        "num": 1,
        "period": "Месяц 1",
        "works": [
            "Разработка и доработка пользовательского портала Farabi School (каталог программ, страницы курсов, единая навигация).",
            "Развитие фирменной темы оформления и единой страницы входа.",
            "Настройка и внедрение единого сценария авторизации (приоритет Univer с контролируемым локальным резервом).",
            "Разработка и доработка личного кабинета студента (обзор, мои курсы, базовые разделы).",
            "Настройка ролей, прав доступа и ограничений навигации для студентов портала.",
            "Стабилизация отображения каталога курсов с учётом видимости курсов в административной панели Moodle.",
            "Базовая адаптация интерфейса под мобильные устройства (ключевые страницы портала и входа).",
        ],
        "result": (
            "Функционирующий пользовательский портал с фирменным оформлением, единой страницей входа, "
            "личным кабинетом и согласованной навигацией; критические дефекты входа и каталога устранены."
        ),
        "acceptance": [
            "Студент может войти через единую форму и попасть в личный кабинет.",
            "Каталог и страницы курсов отображаются в согласованном объёме без блокирующих ошибок.",
            "Скрытые в админке курсы не отображаются в публичном каталоге.",
            "Основные страницы корректно отображаются на мобильных экранах.",
        ],
    },
    {
        "num": 2,
        "period": "Месяц 2",
        "works": [
            "Интеграция с информационной системой Univer: настройка клиента API, безопасная авторизация, синхронизация профиля студента.",
            "Разработка и доработка механизма привязки пользователя Univer к учётной записи Moodle.",
            "Синхронизация учебных дисциплин и автоматическая запись на соответствующие курсы Moodle.",
            "Развитие сервисов предметов, зачисления и кэширования данных кабинета.",
            "Настройка административных параметров интеграции (включение/отключение, резервные методы входа).",
            "Диагностика и стабилизация сценариев входа при недоступности внешней системы.",
        ],
        "result": (
            "Работоспособная интеграция с Univer: вход студента, синхронизация профиля и дисциплин, "
            "запись на курсы Moodle в согласованном объёме."
        ),
        "acceptance": [
            "Студент Univer успешно проходит авторизацию и видит привязанный профиль в кабинете.",
            "Дисциплины синхронизируются и отражаются в разделе «Мои курсы».",
            "Запись на курсы Moodle выполняется по согласованным правилам сопоставления.",
            "При недоступности Univer предусмотренные сценарии обрабатываются без нарушения безопасности.",
        ],
    },
    {
        "num": 3,
        "period": "Месяц 3",
        "works": [
            "Развитие разделов личного кабинета: расписание, экзамены, оценки, посещаемость.",
            "Разработка и доработка отображения учебных данных на основе кэша Univer (без записи в журнал оценок Moodle).",
            "Настройка обновления данных кабинета и индикаторов актуальности.",
            "Доработка прав доступа к разделам кабинета (общие и Univer-связанные вкладки).",
            "Развитие отображения прогресса обучения (XP, достижения) в рамках портала.",
            "Стабилизация сценариев просмотра оценок и посещаемости.",
        ],
        "result": (
            "Личный кабинет с разделами расписания, экзаменов, оценок и посещаемости; "
            "корректное разграничение доступа и обновление данных."
        ),
        "acceptance": [
            "Привязанный студент видит расписание, экзамены, оценки и посещаемость в кабинете.",
            "Данные отображаются из согласованного кэша без блокирующих ошибок.",
            "Несвязанные пользователи не получают доступ к Univer-разделам.",
            "Отсутствуют блокирующие дефекты в реализованных разделах кабинета.",
        ],
    },
    {
        "num": 4,
        "period": "Месяц 4",
        "works": [
            "Разработка и доработка сценариев онлайн-тестирования Moodle Quiz для контрольных мероприятий.",
            "Интеграция Farabi Proctoring (Proctopus): настройка правил доступа к тестам, подготовительная страница экзамена.",
            "Развитие проверки камеры, микрофона, соединения и расширения браузера на этапе подготовки.",
            "Настройка жизненного цикла сессии прокторинга (создание, допуск, завершение, сверка).",
            "Разработка и доработка страницы завершения экзамена и сценария возврата в курс.",
            "Оптимизация UX теста: режим фокуса, скрытие отвлекающих элементов на этапе сдачи.",
            "Настройка приёма результатов прокторинга и журналирования событий.",
        ],
        "result": (
            "Согласованный сценарий сдачи теста с прокторингом: подготовка → допуск → попытка → завершение; "
            "корректное закрытие сессий и отображение статусов."
        ),
        "acceptance": [
            "Студент проходит подготовку и получает доступ к тесту после допуска прокторинга.",
            "Попытка теста создаётся штатными средствами Moodle.",
            "После завершения теста сессия прокторинга закрывается, активные сессии не дублируются.",
            "Отсутствуют блокирующие дефекты в согласованном сценарии сдачи.",
        ],
    },
    {
        "num": 5,
        "period": "Месяц 5",
        "works": [
            "Разработка и доработка сценариев записи на программы и курсы (бесплатные и платные).",
            "Развитие подачи заявки на обучение и отображения статуса заявки.",
            "Подготовка платёжного модуля и интеграция с платёжным API (при предоставлении данных Заказчиком).",
            "Настройка создания платёжной заявки, получения статуса оплаты, обработки успешной и неуспешной оплаты.",
            "Разработка предоставления доступа к обучению после подтверждённой оплаты.",
            "Административный просмотр заявок и платёжных статусов.",
            "Доработка страниц курсов и каталога для сценариев записи и оплаты.",
        ],
        "result": (
            "Функционал записи и оплаты обучения в согласованном объёме: заявки, статусы, "
            "подключение платёжного API (при наличии данных провайдера), предоставление доступа после оплаты."
        ),
        "acceptance": [
            "Пользователь может выбрать программу/курс и подать заявку.",
            "Статусы заявки и оплаты отображаются в согласованном объёме.",
            "После подтверждённой оплаты пользователь получает доступ к обучению.",
            "Администратор может просматривать заявки и статусы платежей.",
            "Данные банковских карт не хранятся в Moodle.",
        ],
    },
    {
        "num": 6,
        "period": "Месяц 6",
        "works": [
            "Стабилизация платформы: устранение выявленных дефектов в разработанном функционале.",
            "Оптимизация производительности ключевых страниц и фоновых задач.",
            "Доработка мобильной адаптации и доступности интерфейса.",
            "Усиление безопасности: контроль доступа, журналирование, обработка ошибок интеграций.",
            "Подготовка эксплуатационной и пользовательской документации.",
            "Сопровождение внедрённых компонентов в рамках согласованного объёма.",
            "Приёмочные работы и передача результатов Заказчику.",
        ],
        "result": (
            "Стабильная образовательная платформа в согласованном объёме; документация; "
            "устранены критические дефекты; проведена приёмка этапа."
        ),
        "acceptance": [
            "Основные пользовательские сценарии работоспособны.",
            "Отсутствуют блокирующие дефекты в реализованном функционале.",
            "Предоставлена согласованная документация.",
            "Результаты этапа приняты или замечания согласованы сторонами.",
        ],
    },
]


def build_tz_sections() -> list[tuple[str, list[str]]]:
    sections: list[tuple[str, list[str]]] = []

    sections.append(("1. Общие положения", [
        "1.1. Настоящее Техническое задание (далее — ТЗ) определяет объём, содержание, сроки и порядок выполнения работ "
        "по разработке, доработке и сопровождению образовательной платформы Farabi School (далее — Платформа) на базе "
        "системы Moodle.",
        "1.2. ТЗ является неотъемлемым приложением к договору гражданско-правового характера (ГПХ) между Заказчиком и Исполнителем.",
        "1.3. Срок выполнения работ: 6 (шесть) календарных месяцев с даты подписания договора.",
        "1.4. Место выполнения работ: дистанционно, с развёртыванием и проверкой на согласованной тестовой и/или "
        "эксплуатационной среде Заказчика.",
        "1.5. Исполнитель выполняет работы без изменения ядра Moodle, в форме плагинов, тем оформления и согласованных настроек.",
        "1.6. Платформа включает: пользовательский портал и личный кабинет; систему Moodle для организации обучения; "
        "интеграцию с Univer; модуль онлайн-прокторинга Farabi Proctoring (Proctopus); сценарии записи и оплаты обучения.",
    ]))

    sections.append(("2. Термины и сокращения", [
        "Платформа — образовательный портал Farabi School на базе Moodle.",
        "Moodle — система управления обучением с открытым исходным кодом.",
        "Univer — внешняя информационная система учёта студентов и учебных данных.",
        "Farabi Proctoring (Proctopus) — внешняя система онлайн-прокторинга.",
        "Личный кабинет — раздел портала для авторизованного пользователя.",
        "Курс — учебный модуль в Moodle.",
        "Прокторинг — контроль сдачи теста с использованием камеры, микрофона и внешней системы.",
        "Заявка — запрос пользователя на запись на программу или курс.",
    ]))

    sections.append(("3. Цели выполнения работ", [
        "3.1. Обеспечение единого пользовательского опыта обучения на платформе Farabi School.",
        "3.2. Интеграция с системой Univer для авторизации, синхронизации учебных данных и зачисления на курсы.",
        "3.3. Организация онлайн-тестирования с поддержкой прокторинга.",
        "3.4. Развитие сценариев записи и оплаты обучения.",
        "3.5. Обеспечение стабильной эксплуатации, безопасности и сопровождения платформы в согласованном объёме.",
    ]))

    sections.append(("4. Общий объём работ", [
        "4.1. Работы включают шесть последовательных этапов (месяцев), каждый из которых завершается отдельным актом выполненных работ.",
        "4.2. Общая стоимость работ по договору: ________________ тенге.",
        "4.3. Стоимость каждого этапа: ________________ тенге.",
        "4.4. Детальный перечень работ, результатов и критериев приёмки по этапам приведён в разделах 5–15 и в календарном плане (раздел 16).",
    ]))

    portal = [
        "5.1. Разработка и доработка маркетингового лендинга и каталога образовательных программ.",
        "5.2. Развитие страниц курсов (описание, программа, трейлер, запись).",
        "5.3. Настройка фирменной темы оформления и единой страницы входа.",
        "5.4. Разработка и доработка личного кабинета студента (обзор, мои курсы).",
        "5.5. Настройка навигации, ролей и ограничений доступа для студентов портала.",
        "5.6. Развитие системы мотивации (XP, достижения) в рамках портала.",
        "5.7. Обеспечение корректного отображения/скрытия курсов в каталоге в соответствии с настройками видимости в Moodle.",
    ]
    sections.append(("5. Разработка и развитие пользовательского портала", portal))

    univer = [
        "6.1. Настройка и развитие интеграции с API Univer (HTTPS, ограничение частоты запросов).",
        "6.2. Реализация сценария единой авторизации: проверка в Univer с контролируемым локальным резервом.",
        "6.3. Синхронизация профиля студента и привязка учётной записи.",
        "6.4. Синхронизация дисциплин и автоматическое зачисление на курсы Moodle.",
        "6.5. Кэширование данных кабинета (расписание, оценки, посещаемость, экзамены).",
        "6.6. Настройка административных параметров интеграции и диагностических инструментов.",
        "6.7. Обработка сценариев недоступности внешней системы без компрометации безопасности.",
    ]
    sections.append(("6. Интеграция с Univer", univer))

    cabinet = [
        "7.1. Развитие разделов личного кабинета: обзор, мои курсы, расписание, оценки, посещаемость.",
        "7.2. Отображение учебных данных на основе кэша Univer (без записи оценок Univer в журнал Moodle).",
        "7.3. Индикаторы актуальности данных и механизм обновления.",
        "7.4. Разграничение доступа: общие разделы для всех пользователей, Univer-разделы — для привязанных студентов.",
        "7.5. Отображение прогресса обучения (XP, уровни, достижения).",
    ]
    sections.append(("7. Личный кабинет и учебные данные", cabinet))

    courses = [
        "8.1. Организация структуры курсов и модулей обучения в Moodle.",
        "8.2. Настройка зачисления (enrolment) на курсы: вручную, автоматически по интеграции, по заявке.",
        "8.3. Управление видимостью курсов в каталоге портала.",
        "8.4. Развитие сценариев перехода от каталога к обучению (программа курса, продолжение обучения).",
    ]
    sections.append(("8. Курсы и организация обучения", courses))

    quiz = [
        "9.1. Настройка и доработка сценариев Moodle Quiz для модульных и итоговых тестов.",
        "9.2. Развитие пользовательского интерфейса теста (режим фокуса, скрытие отвлекающих элементов).",
        "9.3. Настройка правил доступа к тестам.",
    ]
    sections.append(("9. Онлайн-тестирование", quiz))

    proct = [
        "10.1. Интеграция Farabi Proctoring (Proctopus) с Moodle Quiz через согласованные модули платформы.",
        "10.2. Подготовительная страница экзамена: проверка камеры, микрофона, соединения, расширения.",
        "10.3. Сценарий запуска прокторинга, допуска и возврата к тесту.",
        "10.4. Управление жизненным циклом сессии (создание, допуск, завершение, сверка, истечение).",
        "10.5. Страница завершения экзамена и интеграция с результатами прокторинга.",
        "10.6. Журналирование событий прокторинга без хранения чувствительных данных в открытом виде.",
        "10.7. Настройка правил прокторинга для отдельных тестов (включение/отключение по курсу/тесту).",
    ]
    sections.append(("10. Farabi Proctoring / Proctopus", proct))

    payment = [
        "11.1. Разработка сценариев записи на программы и курсы (бесплатные и платные).",
        "11.2. Подача заявки и отображение статуса заявки.",
        "11.3. Подготовка платёжного модуля и интеграция с платёжным API провайдера.",
        "11.4. Создание платёжной заявки, получение и обработка статуса оплаты.",
        "11.5. Предоставление доступа к обучению после подтверждённой оплаты.",
        "11.6. Административный просмотр заявок и платёжных статусов.",
        "11.7. Непосредственное подключение платёжного провайдера зависит от своевременного предоставления "
        "Заказчиком API, документации, учётных данных и тестовой среды платёжной системы.",
        "11.8. Moodle не хранит данные банковских карт.",
    ]
    sections.append(("11. Запись и оплата обучения", payment))

    admin = [
        "12.1. Административные настройки плагинов портала, интеграции и прокторинга.",
        "12.2. Инструменты диагностики и административные утилиты для сопровождения платформы.",
        "12.3. Просмотр заявок, платёжных статусов и журналов событий.",
        "12.4. Управление видимостью курсов и параметрами каталога.",
    ]
    sections.append(("12. Административный функционал", admin))

    ui = [
        "13.1. Доработка UI/UX ключевых страниц портала, кабинета, курсов и тестов.",
        "13.2. Мобильная адаптация (responsive) основных пользовательских сценариев.",
        "13.3. Многоязычность интерфейса (русский, казахский, английский) в согласованном объёме.",
        "13.4. Доступность: текстовые подписи, контраст, корректное отображение статусов.",
    ]
    sections.append(("13. UI/UX и мобильная адаптация", ui))

    security = [
        "14.1. Использование HTTPS для всех пользовательских сценариев.",
        "14.2. Безопасное хранение ключей и секретов (только в конфигурации сервера, не в репозитории).",
        "14.3. Ограничение частоты запросов при авторизации.",
        "14.4. Защита от несанкционированного доступа к разделам кабинета (привязка к сессии пользователя).",
        "14.5. Журналирование событий интеграций и прокторинга без записи паролей и токенов.",
        "14.6. Контроль перенаправлений (защита от open redirect).",
    ]
    sections.append(("14. Требования к безопасности", security))

    support = [
        "15.1. В рамках согласованного объёма Исполнитель оказывает сопровождение разработанного функционала:",
        "— диагностика ошибок;",
        "— исправление дефектов в разработанных модулях;",
        "— небольшие корректировки интерфейса и логики;",
        "— поддержка созданных интеграций при доступности внешних систем;",
        "— консультации по настройке;",
        "— контроль работоспособности внедрённых компонентов.",
        "15.2. Сопровождение не включает круглосуточную поддержку (24/7).",
        "15.3. Исполнитель не несёт ответственности за бесперебойную работу внешних систем (Univer, Proctopus, "
        "платёжные провайдеры, хостинг, сеть).",
        "15.4. Сопровождение распределено по всем этапам; на этапе 6 — усиленный объём стабилизации и приёмки.",
    ]
    sections.append(("15. Сопровождение", support))

    return sections


def general_acceptance() -> list[str]:
    return [
        "Функциональность реализована в согласованном объёме этапа.",
        "Основные пользовательские сценарии работоспособны.",
        "Отсутствуют блокирующие дефекты в реализованном функционале этапа.",
        "Результат этапа предоставлен Заказчику в согласованной форме.",
        "Выявленные замечания устранены либо согласованы сторонами в письменной форме.",
        "Существующий функционал не нарушен внесёнными изменениями.",
        "Интеграции работают при условии доступности соответствующих внешних систем и предоставления "
        "необходимых данных Заказчиком.",
    ]


def external_dependencies() -> list[str]:
    return [
        "Выполнение интеграционных работ зависит от своевременного предоставления Заказчиком: API, документации, "
        "учётных записей, разрешений, сетевых доступов, сертификатов, ключей, тестовых данных и информации "
        "от владельцев внешних систем.",
        "Особенно для: Univer, Farabi Proctoring (Proctopus), платёжной системы.",
        "Сбои или недоступность внешнего сервиса не являются автоматически дефектом разработанного Исполнителем функционала.",
        "Исполнитель не изменяет ядро Moodle.",
        "Развёртывание на эксплуатационной среде выполняется при наличии доступа и согласованного окна работ.",
        "Производительность платформы зависит от ресурсов сервера; рекомендации по ресурсам предоставляются "
        "Исполнителем по запросу.",
    ]


def acceptance_procedure() -> list[str]:
    return [
        "По завершении каждого этапа Исполнитель уведомляет Заказчика и передаёт результат этапа.",
        "Заказчик в согласованный срок проверяет результат и подписывает Акт выполненных работ либо направляет "
        "мотивированные замечания.",
        "При наличии замечаний Исполнитель устраняет их в согласованный срок.",
        "Оплата этапа производится на основании подписанного Акта.",
        "Итоговая приёмка — по завершении 6-го этапа.",
    ]


def calendar_table_rows(*, full: bool = False) -> list[list[str]]:
    rows = []
    for s in STAGES:
        if full:
            works = "; ".join(s["works"])
        else:
            works = "; ".join(s["works"][:4])
            if len(s["works"]) > 4:
                works += f"; и др. (всего {len(s['works'])} работ)"
        rows.append([
            str(s["num"]),
            s["period"],
            works,
            s["result"],
            f"Акт выполненных работ №{s['num']}",
        ])
    return rows


def generate_tz_docx(path: Path) -> None:
    doc = Document()
    set_doc_defaults(doc)

    add_title(doc, "ТЕХНИЧЕСКОЕ ЗАДАНИЕ")
    add_title(doc, "на разработку, доработку и сопровождение")
    add_title(doc, "образовательной платформы Farabi School")
    doc.add_paragraph()
    add_p(doc, "Приложение к договору ГПХ № ____________ от «____» __________ 20__ г.", bold=False)
    add_p(doc, f"Дата подготовки документа: {date.today().strftime('%d.%m.%Y')}", bold=False)
    doc.add_paragraph()

    for title, paras in build_tz_sections():
        add_h(doc, title, level=1)
        for para in paras:
            add_p(doc, para)

    add_h(doc, "16. Календарный план выполнения работ", level=1)
    headers = ["№ этапа", "Период", "Перечень работ", "Результат этапа", "Основание для акта"]
    add_table(doc, headers, calendar_table_rows(full=True))

    add_h(doc, "17. Результаты этапов", level=1)
    for s in STAGES:
        add_p(doc, f"Этап {s['num']} ({s['period']})", bold=True)
        add_p(doc, "Перечень работ этапа:")
        add_bullets(doc, s["works"])
        add_p(doc, f"Результат этапа: {s['result']}")

    add_h(doc, "18. Критерии приемки", level=1)
    add_bullets(doc, general_acceptance())
    for s in STAGES:
        add_p(doc, f"Дополнительно для этапа {s['num']}:", bold=True)
        add_bullets(doc, s["acceptance"])

    add_h(doc, "19. Внешние зависимости и ограничения", level=1)
    add_bullets(doc, external_dependencies())

    add_h(doc, "20. Порядок сдачи и приемки работ", level=1)
    add_bullets(doc, acceptance_procedure())

    add_h(doc, "21. Подписи сторон", level=1)
    doc.add_paragraph()
    t = doc.add_table(rows=2, cols=2)
    t.cell(0, 0).text = "Заказчик:\n\nФИО: ______________________\n\nПодпись: _______________\n\nДата: «___» ______ 20__ г."
    t.cell(0, 1).text = "Исполнитель:\n\nФИО: ______________________\n\nПодпись: _______________\n\nДата: «___» ______ 20__ г."

    doc.save(str(path))


def generate_acts_docx(path: Path) -> None:
    doc = Document()
    set_doc_defaults(doc)
    add_title(doc, "ШАБЛОНЫ АКТОВ ВЫПОЛНЕННЫХ РАБОТ")
    add_title(doc, "к договору ГПХ на разработку платформы Farabi School")
    doc.add_paragraph()

    for s in STAGES:
        add_h(doc, f"АКТ №{s['num']}", level=1)
        add_p(doc, "к договору № ____________ от «____» __________ 20__ г.")
        add_p(doc, f"Отчётный период: {s['period']} (__________ — __________ 20__ г.)")
        add_p(doc, "Исполнителем выполнены следующие работы:", bold=True)
        for i, w in enumerate(s["works"], 1):
            add_p(doc, f"{i}. {w}")
        add_p(doc, "Результат выполненных работ:", bold=True)
        add_p(doc, s["result"])
        add_p(doc, "Стоимость выполненных работ: ________________ тенге.")
        add_p(doc, "Работы выполнены в согласованном объёме.")
        add_p(doc, "Замечания Заказчика: ________________________________________________")
        doc.add_paragraph()
        t = doc.add_table(rows=2, cols=2)
        t.cell(0, 0).text = "Заказчик:\nФИО: __________________\nПодпись: __________\nДата: __________"
        t.cell(0, 1).text = "Исполнитель:\nФИО: __________________\nПодпись: __________\nДата: __________"
        if s["num"] < 6:
            doc.add_page_break()

    doc.save(str(path))


def pdf_styles(regular: str, bold: str) -> dict[str, ParagraphStyle]:
    base = getSampleStyleSheet()
    return {
        "title": ParagraphStyle(
            "title", parent=base["Normal"], fontName=bold, fontSize=14,
            alignment=TA_CENTER, spaceAfter=12, leading=18,
        ),
        "h1": ParagraphStyle(
            "h1", parent=base["Normal"], fontName=bold, fontSize=12,
            spaceBefore=14, spaceAfter=8, leading=15,
        ),
        "body": ParagraphStyle(
            "body", parent=base["Normal"], fontName=regular, fontSize=11,
            alignment=TA_JUSTIFY, spaceAfter=6, leading=14, firstLineIndent=20,
        ),
        "bullet": ParagraphStyle(
            "bullet", parent=base["Normal"], fontName=regular, fontSize=11,
            leftIndent=18, spaceAfter=3, leading=13,
        ),
        "small": ParagraphStyle(
            "small", parent=base["Normal"], fontName=regular, fontSize=9,
            leading=11,
        ),
    }


def generate_tz_pdf(path: Path, regular: str, bold: str) -> None:
    styles = pdf_styles(regular, bold)
    doc = SimpleDocTemplate(
        str(path), pagesize=A4,
        leftMargin=2.5 * cm, rightMargin=1.5 * cm,
        topMargin=2 * cm, bottomMargin=2 * cm,
    )
    story: list = []
    story.append(Paragraph("ТЕХНИЧЕСКОЕ ЗАДАНИЕ", styles["title"]))
    story.append(Paragraph("на разработку, доработку и сопровождение<br/>образовательной платформы Farabi School", styles["title"]))
    story.append(Spacer(1, 12))
    story.append(Paragraph("Приложение к договору ГПХ № ____________ от «____» __________ 20__ г.", styles["body"]))
    story.append(Spacer(1, 12))

    for title, paras in build_tz_sections():
        story.append(Paragraph(title, styles["h1"]))
        for para in paras:
            story.append(Paragraph(para, styles["body"]))

    story.append(Paragraph("16. Календарный план выполнения работ", styles["h1"]))
    table_data = [["№", "Период", "Перечень работ", "Результат этапа", "Акт"]]
    for row in calendar_table_rows():
        table_data.append([
            row[0], row[1],
            row[2][:180] + ("…" if len(row[2]) > 180 else ""),
            row[3][:100] + ("…" if len(row[3]) > 100 else ""),
            row[4],
        ])
    tbl = Table(table_data, colWidths=[0.8 * cm, 1.8 * cm, 5.5 * cm, 5.5 * cm, 2.5 * cm])
    tbl.setStyle(TableStyle([
        ("FONTNAME", (0, 0), (-1, 0), bold),
        ("FONTNAME", (0, 1), (-1, -1), regular),
        ("FONTSIZE", (0, 0), (-1, -1), 7),
        ("GRID", (0, 0), (-1, -1), 0.5, colors.grey),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#f0f0f0")),
    ]))
    story.append(tbl)
    story.append(Spacer(1, 12))

    story.append(Paragraph("17. Результаты этапов", styles["h1"]))
    for s in STAGES:
        story.append(Paragraph(f"<b>Этап {s['num']} ({s['period']})</b>", styles["body"]))
        story.append(Paragraph("Перечень работ этапа:", styles["body"]))
        for w in s["works"]:
            story.append(Paragraph(f"• {w}", styles["bullet"]))
        story.append(Paragraph(f"<b>Результат этапа:</b> {s['result']}", styles["body"]))
        story.append(Spacer(1, 6))

    story.append(Paragraph("18. Критерии приемки", styles["h1"]))
    for item in general_acceptance():
        story.append(Paragraph(f"• {item}", styles["bullet"]))
    for s in STAGES:
        story.append(Paragraph(f"<b>Дополнительно для этапа {s['num']}:</b>", styles["body"]))
        for item in s["acceptance"]:
            story.append(Paragraph(f"• {item}", styles["bullet"]))

    story.append(Paragraph("19. Внешние зависимости и ограничения", styles["h1"]))
    for item in external_dependencies():
        story.append(Paragraph(f"• {item}", styles["bullet"]))

    story.append(Paragraph("20. Порядок сдачи и приемки работ", styles["h1"]))
    for item in acceptance_procedure():
        story.append(Paragraph(f"• {item}", styles["bullet"]))

    story.append(Paragraph("21. Подписи сторон", styles["h1"]))
    story.append(Paragraph(
        "Заказчик: ФИО __________________  Подпись __________  Дата «___» ______ 20__ г.<br/>"
        "Исполнитель: ФИО __________________  Подпись __________  Дата «___» ______ 20__ г.",
        styles["body"],
    ))

    def footer(canvas, doc_obj):  # noqa: ANN001
        canvas.saveState()
        canvas.setFont(regular, 9)
        canvas.drawCentredString(A4[0] / 2, 1 * cm, f"Стр. {canvas.getPageNumber()}")
        canvas.restoreState()

    doc.build(story, onFirstPage=footer, onLaterPages=footer)


def generate_acts_pdf(path: Path, regular: str, bold: str) -> None:
    styles = pdf_styles(regular, bold)
    doc = SimpleDocTemplate(str(path), pagesize=A4, leftMargin=2.5 * cm, rightMargin=1.5 * cm, topMargin=2 * cm, bottomMargin=2 * cm)
    story: list = []
    story.append(Paragraph("ШАБЛОНЫ АКТОВ ВЫПОЛНЕННЫХ РАБОТ", styles["title"]))
    story.append(Spacer(1, 12))

    for s in STAGES:
        if s["num"] > 1:
            story.append(PageBreak())
        story.append(Paragraph(f"АКТ №{s['num']}", styles["h1"]))
        story.append(Paragraph(f"к договору № ____________ от «____» __________ 20__ г.", styles["body"]))
        story.append(Paragraph(f"Отчётный период: {s['period']}", styles["body"]))
        story.append(Paragraph("<b>Исполнителем выполнены следующие работы:</b>", styles["body"]))
        for i, w in enumerate(s["works"], 1):
            story.append(Paragraph(f"{i}. {w}", styles["bullet"]))
        story.append(Paragraph("<b>Результат:</b> " + s["result"], styles["body"]))
        story.append(Paragraph("Стоимость: ________________ тенге.", styles["body"]))
        story.append(Paragraph("Заказчик: __________  Исполнитель: __________", styles["body"]))

    def footer(canvas, doc_obj):  # noqa: ANN001
        canvas.saveState()
        canvas.setFont(regular, 9)
        canvas.drawCentredString(A4[0] / 2, 1 * cm, f"Стр. {canvas.getPageNumber()}")
        canvas.restoreState()

    doc.build(story, onFirstPage=footer, onLaterPages=footer)


def write_tz_markdown(path: Path) -> None:
    lines = [
        "# ТЕХНИЧЕСКОЕ ЗАДАНИЕ",
        "## на разработку, доработку и сопровождение образовательной платформы Farabi School",
        "",
        "Приложение к договору ГПХ № ____________ от «____» __________ 20__ г.",
        "",
    ]
    for title, paras in build_tz_sections():
        lines.append(f"## {title}")
        lines.append("")
        for p in paras:
            lines.append(p)
            lines.append("")

    lines.append("## 16. Календарный план выполнения работ")
    lines.append("")
    lines.append("| № этапа | Период | Перечень работ | Результат этапа | Основание для акта |")
    lines.append("|---------|--------|----------------|-----------------|-------------------|")
    for row in calendar_table_rows(full=True):
        lines.append(f"| {row[0]} | {row[1]} | {row[2]} | {row[3]} | {row[4]} |")
    lines.append("")

    lines.append("## 17. Результаты этапов")
    lines.append("")
    for s in STAGES:
        lines.append(f"### Этап {s['num']} ({s['period']})")
        lines.append("")
        lines.append("Перечень работ этапа:")
        for w in s["works"]:
            lines.append(f"- {w}")
        lines.append("")
        lines.append(f"**Результат этапа:** {s['result']}")
        lines.append("")

    lines.append("## 18. Критерии приемки")
    lines.append("")
    for item in general_acceptance():
        lines.append(f"- {item}")
    lines.append("")
    for s in STAGES:
        lines.append(f"**Дополнительно для этапа {s['num']}:**")
        for item in s["acceptance"]:
            lines.append(f"- {item}")
        lines.append("")

    lines.append("## 19. Внешние зависимости и ограничения")
    lines.append("")
    for item in external_dependencies():
        lines.append(f"- {item}")
    lines.append("")

    lines.append("## 20. Порядок сдачи и приемки работ")
    lines.append("")
    for item in acceptance_procedure():
        lines.append(f"- {item}")
    lines.append("")

    lines.append("## 21. Подписи сторон")
    lines.append("")
    lines.append("Заказчик: ФИО __________________  Подпись __________  Дата «___» ______ 20__ г.")
    lines.append("")
    lines.append("Исполнитель: ФИО __________________  Подпись __________  Дата «___» ______ 20__ г.")
    lines.append("")

    path.write_text("\n".join(lines), encoding="utf-8")


def validate_files(files: list[Path]) -> dict:
    result = {"ok": True, "details": [], "tz_pages": 0, "acts_count": 0, "secrets": 0}
    secret_patterns = ["password", "api_key", "secret_key", "Bearer ", "sk_live", "BEGIN RSA"]
    for f in files:
        if not f.exists():
            result["ok"] = False
            result["details"].append(f"MISSING: {f.name}")
            continue
        size = f.stat().st_size
        if size < 1000:
            result["ok"] = False
            result["details"].append(f"TOO_SMALL: {f.name} ({size} bytes)")
        else:
            result["details"].append(f"OK: {f.name} ({size // 1024} KB)")
        if f.suffix == ".docx":
            try:
                d = Document(str(f))
                paras = len(d.paragraphs)
                if "TZ_" in f.name:
                    result["tz_pages"] = max(result["tz_pages"], paras // 25 + 1)
                if "ACTS_" in f.name:
                    result["acts_count"] = sum(1 for p in d.paragraphs if p.text.startswith("АКТ №"))
            except Exception as e:  # noqa: BLE001
                result["ok"] = False
                result["details"].append(f"CORRUPT DOCX {f.name}: {e}")
        if f.suffix == ".pdf":
            try:
                from pypdf import PdfReader
                reader = PdfReader(str(f))
                pages = len(reader.pages)
                text = " ".join(page.extract_text() or "" for page in reader.pages)
                if "TZ_" in f.name:
                    result["tz_pages"] = pages
                for pat in secret_patterns:
                    if pat.lower() in text.lower():
                        result["secrets"] += 1
                if "ТЕХНИЧЕСКОЕ ЗАДАНИЕ" not in text and "TZ_" in f.name:
                    result["ok"] = False
                    result["details"].append(f"PDF_CYRILLIC_ISSUE: {f.name}")
                if "АКТ №" not in text and "ACTS_" in f.name:
                    result["ok"] = False
                    result["details"].append(f"PDF_ACTS_MISSING: {f.name}")
            except ImportError:
                result["details"].append(f"PDF pages not counted (pypdf missing): {f.name}")
            except Exception as e:  # noqa: BLE001
                result["ok"] = False
                result["details"].append(f"PDF_ERROR {f.name}: {e}")
    if result["acts_count"] != 6:
        result["details"].append(f"ACTS_COUNT: {result['acts_count']} (expected 6)")
    return result


def main() -> int:
    regular, bold = register_pdf_fonts()
    out = BASE
    files = {
        "tz_docx": out / "TZ_FarabiSchool_GPH_6_months.docx",
        "tz_pdf": out / "TZ_FarabiSchool_GPH_6_months.pdf",
        "acts_docx": out / "ACTS_FarabiSchool_6_months.docx",
        "acts_pdf": out / "ACTS_FarabiSchool_6_months.pdf",
        "tz_md": out / "TZ_FarabiSchool_GPH_6_months.md",
    }

    print("Generating TZ DOCX...")
    generate_tz_docx(files["tz_docx"])
    print("Generating ACTS DOCX...")
    generate_acts_docx(files["acts_docx"])
    print("Generating TZ PDF...")
    generate_tz_pdf(files["tz_pdf"], regular, bold)
    print("Generating ACTS PDF...")
    generate_acts_pdf(files["acts_pdf"], regular, bold)
    print("Generating TZ MD...")
    write_tz_markdown(files["tz_md"])

    val = validate_files([files["tz_docx"], files["tz_pdf"], files["acts_docx"], files["acts_pdf"]])
    print("\nValidation:")
    for d in val["details"]:
        print(" ", d)
    print(f"\nTZ pages (PDF): {val.get('tz_pages', '?')}")
    print(f"ACTS count (DOCX): {val.get('acts_count', '?')}")
    print(f"Secrets found: {val.get('secrets', 0)}")
    return 0 if val["ok"] else 1


if __name__ == "__main__":
    sys.exit(main())
