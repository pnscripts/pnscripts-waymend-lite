"""Writes languages/pnscripts-waymend-lite-bg_BG.po from languages/pnscripts-waymend-lite.pot (keys are the msgids exactly as
written in the .pot, escapes included). Plural entries map to a pair (one, many)."""
import re
from pathlib import Path

BG = {
    "Waymend by PN Scripts": "Waymend by PN Scripts",
    "https://pnscripts.com": "https://pnscripts.com",
    "Redirect manager: 301, 302, 307 and 410 rules with exact, prefix and regex matching, CSV import and export, hit counts, a 404 log with one-click redirects and loop detection.":
        "Мениджър на пренасочвания: правила 301, 302, 307 и 410 с точно, префиксно и regex съвпадение, CSV внос и износ, брояч на посещения, дневник на 404 грешки с пренасочване с едно щракване и откриване на цикли.",
    "PN Scripts": "PN Scripts",
    "Waymend redirects": "Пренасочвания Waymend",
    "Redirects": "Пренасочвания",
    "You do not have permission to manage redirects.": "Нямате права да управлявате пренасочванията.",
    "404 log": "404 дневник",
    "Import and export": "Внос и износ",
    "Settings": "Настройки",
    "Add redirect": "Добавяне на пренасочване",
    "Search redirects": "Търсене",
    "Test a URL": "Проверка на адрес",
    "Trace": "Проследяване",
    "rule #%d": "правило №%d",
    "Loop: these rules send the visitor around in circles. Waymend stops at the URL it would redirect to itself, but fix the rules.":
        "Цикъл: тези правила въртят посетителя в кръг. Waymend спира при адреса, който би пренасочил към себе си, но поправете правилата.",
    "No rule matches this URL; WordPress handles it as usual.": "Нито едно правило не съвпада с този адрес; WordPress го обработва както обикновено.",
    "%d redirect in a row. Point the first rule at the final URL to save visitors a round trip.":
        ("%d пренасочване подред. Насочете първото правило директно към крайния адрес, за да спестите на посетителите едно отиване и връщане.",
         "%d пренасочвания подред. Насочете първото правило директно към крайния адрес, за да спестите на посетителите излишните стъпки."),
    "One redirect, no loop.": "Едно пренасочване, без цикъл.",
    "That redirect does not exist.": "Това пренасочване не съществува.",
    "301 Moved permanently": "301 Преместено за постоянно",
    "302 Found (temporary)": "302 Временно преместено",
    "307 Temporary redirect (keeps the method)": "307 Временно пренасочване (запазва метода)",
    "410 Gone (no target)": "410 Премахнато (без цел)",
    "Exact: this URL only": "Точно: само този адрес",
    "Prefix: this path and everything below it; the rest is appended to the target": "Префикс: този път и всичко под него; остатъкът се добавя към целта",
    "Regex: a regular expression; use $1, $2 in the target": "Regex: регулярен израз; в целта използвайте $1, $2",
    "Ignore: match the path, drop the visitor's query string": "Пропускане: съвпадение по пътя, query string-ът на посетителя отпада",
    "Pass: match the path, append the visitor's query string to the target": "Предаване: съвпадение по пътя, query string-ът се добавя към целта",
    "Exact: the source's query parameters must be present, in any order": "Точно: параметрите от източника трябва да присъстват, в произволен ред",
    "Edit redirect": "Редактиране на пренасочване",
    "Source": "Източник",
    r"A path on this site, such as /old-page/. For regex rules, a pattern such as ^/blog/(\\d{4})/(.+)$.":
        r"Път в този сайт, например /old-page/. За regex правила: шаблон, например ^/blog/(\\d{4})/(.+)$.",
    "Target": "Цел",
    "A path on this site (/new-page/) or a full URL. Leave empty for 410 Gone.": "Път в този сайт (/new-page/) или пълен адрес. Оставете празно за 410.",
    "Suggested from a published item with a matching slug. Check it before saving.": "Предложено по публикувано съдържание с подобен slug. Проверете го, преди да запишете.",
    "Status": "Статус",
    "Match": "Съвпадение",
    "Case-sensitive": "Различаване на главни и малки букви",
    "Query string": "Query string",
    "Order": "Ред",
    "Regex rules are tried from the lowest number up. Exact rules always come first, then the longest prefix.":
        "Regex правилата се проверяват от най-малкото число нагоре. Първо винаги са точните правила, после най-дългият префикс.",
    "Enabled": "Включено",
    "Active on the site": "Действа в сайта",
    "Save redirect": "Запазване",
    "404 logging is switched off in the settings.": "Записването на 404 грешки е изключено в настройките.",
    "Search 404s": "Търсене",
    "Clear the whole log": "Изчистване на целия дневник",
    "Delete every logged 404?": "Да се изтрият ли всички записани 404 грешки?",
    "Import CSV": "Внос от CSV",
    "Columns: source, target, match, status, query, case_sensitive, enabled. Only source and target are required; a file without a header row is read as source, target, status, match. Comma, semicolon and tab separators work.":
        "Колони: source, target, match, status, query, case_sensitive, enabled. Задължителни са само source и target; файл без заглавен ред се чете като source, target, status, match. Работят разделители запетая, точка и запетая и табулация.",
    "Skip rows whose source already has a rule": "Пропускане на редове, чийто източник вече има правило",
    "Update the existing rule with the row": "Обновяване на съществуващото правило с данните от реда",
    "Import": "Внос",
    "Export CSV": "Износ в CSV",
    "Downloads every rule with its hit count and last hit, in the same format the importer reads.": "Изтегля всички правила с броя посещения и последното посещение, в същия формат, който вносът чете.",
    "Download CSV": "Изтегляне на CSV",
    "Record URLs that return 404": "Записване на адресите, които връщат 404",
    "Stores the path, the number of hits, first and last time and the last referrer. No IP addresses or user agents.":
        "Пази пътя, броя посещения, първото и последното време и последния източник. Без IP адреси и user agent.",
    "Keep at most": "Пазене на най-много",
    "distinct 404 URLs; older ones are removed once a day.": "различни 404 адреса; по-старите се изтриват веднъж дневно.",
    "Do not log": "Без записване на",
    "One pattern per line, * matches anything. The defaults skip common vulnerability scans such as *.php and /.env.":
        "По един шаблон на ред, * съвпада с всичко. По подразбиране се пропускат честите сканирания за уязвимости като *.php и /.env.",
    "Default status for new redirects": "Статус по подразбиране за нови пренасочвания",
    "Redirect saved.": "Пренасочването е запазено.",
    "Redirect added.": "Пренасочването е добавено.",
    "Redirect deleted.": "Пренасочването е изтрито.",
    "Redirect disabled.": "Пренасочването е изключено.",
    "Redirect enabled.": "Пренасочването е включено.",
    "%d entry deleted.": ("%d запис е изтрит.", "%d записа са изтрити."),
    "%d redirect deleted.": ("%d пренасочване е изтрито.", "%d пренасочвания са изтрити."),
    "%d redirect updated.": ("%d пренасочване е обновено.", "%d пренасочвания са обновени."),
    "Hit count reset for %d redirect.": ("Броячът е нулиран за %d пренасочване.", "Броячите са нулирани за %d пренасочвания."),
    "The 404 log is empty.": "404 дневникът е празен.",
    "Choose a CSV file to import.": "Изберете CSV файл за внос.",
    "The file is larger than 5 MB.": "Файлът е по-голям от 5 MB.",
    "Import finished: %1$d added, %2$d updated, %3$d skipped, %4$d rejected.": "Вносът приключи: добавени %1$d, обновени %2$d, пропуснати %3$d, отхвърлени %4$d.",
    "Line %1$d: %2$s": "Ред %1$d: %2$s",
    "Settings saved.": "Настройките са запазени.",
    "URL": "Адрес",
    "Hits": "Посещения",
    "Last seen": "Последно",
    "First seen": "Първо",
    "Last referrer": "Последен източник",
    "Delete": "Изтриване",
    "No 404 errors logged.": "Няма записани 404 грешки.",
    "Redirect this": "Пренасочи",
    "Open": "Отваряне",
    "none": "няма",
    "%s ago": "преди %s",
    "Enter the old URL (source).": "Въведете стария адрес (източник).",
    "The source must be a path on this site, such as /old-page.": "Източникът трябва да е път в този сайт, например /old-page.",
    "Enter the target URL, or choose 410 Gone.": "Въведете целевия адрес или изберете 410.",
    "The target must be a path or an http(s) URL.": "Целта трябва да е път или http(s) адрес.",
    "Choose 301, 302, 307 or 410.": "Изберете 301, 302, 307 или 410.",
    "Choose exact, prefix or regex matching.": "Изберете точно, префиксно или regex съвпадение.",
    "Choose how the query string is handled.": "Изберете как да се обработва query string-ът.",
    "The regular expression is not valid: %s": "Регулярният израз не е валиден: %s",
    "Rule #%s already uses this source with the same match type.": "Правило №%s вече използва този източник със същия вид съвпадение.",
    "This rule would create a redirect loop: following it brings the visitor back to a URL already visited.":
        "Това правило би създало цикъл: следвайки го, посетителят се връща на адрес, през който вече е минал.",
    "The target is redirected again; visitors end up at %s. Consider pointing this rule there directly.":
        "Целта се пренасочва отново; посетителите стигат до %s. По-добре насочете правилото направо там.",
    r'Query mode \"exact\" is selected but the source has no query string, so only requests without one will match.':
        r'Избран е режим „точно“ за query string-а, но източникът няма такъв, затова ще съвпадат само заявки без параметри.',
    "The target contains $1-style placeholders, which are only replaced for regex rules.": "Целта съдържа заместители като $1, които се попълват само при regex правила.",
    "Last hit": "Последно посещение",
    "Enable": "Включване",
    "Disable": "Изключване",
    "Reset hit counts": "Нулиране на броячите",
    "All": "Всички",
    "Active": "Активни",
    "Inactive": "Неактивни",
    "410 Gone": "410 Премахнати",
    "No redirects yet. Add one above, import a CSV file or create one from the 404 log.": "Още няма пренасочвания. Добавете от бутона горе, внесете CSV файл или създайте от 404 дневника.",
    "Edit": "Редактиране",
    "Delete this redirect?": "Да се изтрие ли това пренасочване?",
    "disabled": "изключено",
    "(none, answers 410 Gone)": "(няма, отговаря с 410)",
    "Exact": "Точно",
    "Prefix": "Префикс",
    "Regex": "Regex",
    "passes query": "предава query",
    "exact query": "точен query",
    "case-sensitive": "главни/малки букви",
    "never": "никога",
    "Check old URLs": "Проверка на стари адреси",
    "Moving or redesigning a site? Paste the old addresses (from the old sitemap, Search Console or an analytics export), one per line. The old domain is fine: each path is checked on this site, the way a visitor following an old link would land.":
        "Местите или преработвате сайт? Поставете старите адреси (от стария sitemap, Search Console или експорт от анализите), по един на ред. Старият домейн не пречи: всеки път се проверява на този сайт, точно както би стигнал посетител по стара връзка.",
    "Old URLs, one per line": "Стари адреси, по един на ред",
    "Check URLs": "Провери адресите",
    "Up to %d URLs per check.": "До %d адреса на проверка.",
    "Not checked (time limit, run again): %d": "Непроверени (лимит на времето, пуснете отново): %d",
    "Download the %d URL that needs a redirect (CSV for the importer)":
        ("Изтегли %d адрес, който има нужда от пренасочване (CSV за вноса)",
         "Изтегли %d адреса, които имат нужда от пренасочване (CSV за вноса)"),
    "Old URL": "Стар адрес",
    "Result": "Резултат",
    "Lands on": "Стига до",
    "Suggested: %s": "Предложение: %s",
    "The first 300 results are shown; the CSV has every URL that needs a redirect.": "Показани са първите 300 резултата; CSV файлът съдържа всички адреси, които имат нужда от пренасочване.",
    "Missing, needs a redirect": "Липсва, трябва пренасочване",
    "Redirected to a missing page": "Пренасочен към липсваща страница",
    "Redirect loop": "Цикъл на пренасочване",
    "Could not be checked": "Не можа да се провери",
    "Sent to another site": "Пратен към друг сайт",
    "Gone (410) on purpose": "Премахнат нарочно (410)",
    "Redirected, works": "Пренасочен, работи",
    "Works": "Работи",
    "(%d, WordPress redirects it further)": "(%d, WordPress го пренасочва нататък)",
    "Paste at least one old URL or path, one per line.": "Поставете поне един стар адрес или път, по един на ред.",
    "%d line is not a URL and was skipped.":
        ("%d ред не е адрес и беше пропуснат.", "%d реда не са адреси и бяха пропуснати."),
    "Only the first %d URLs were checked.": "Проверени са само първите %d адреса.",
    "PN Scripts Waymend Lite – Redirects, 404 Log and Old URL Check": "PN Scripts Waymend Lite – пренасочвания, 404 дневник и проверка на стари адреси",
    "https://pnscripts.com/marketplace/wp-waymend": "https://pnscripts.com/marketplace/wp-waymend",
    "Keep old links working: 301, 302, 307 and 410 redirects by exact URL or prefix, a 404 log with one-click redirects, loop protection, and a check of the old site's URLs before a move.":
        "Старите връзки продължават да работят: пренасочвания 301, 302, 307 и 410 по точен адрес или префикс, дневник на 404 грешки с пренасочване с едно щракване, защита от цикли и проверка на адресите на стария сайт преди преместване.",
    "Check old URLs and export": "Проверка на стари адреси и износ",
    "Download the %d URL that needs a redirect (CSV)":
        ("Изтегли %d адрес, който има нужда от пренасочване (CSV)", "Изтегли %d адреса, които имат нужда от пренасочване (CSV)"),
    "Waymend (full edition) is active, so Waymend Lite is not running. You can deactivate Waymend Lite.": "Waymend (пълната версия) е активен, затова Waymend Lite не работи. Можете да деактивирате Waymend Lite.",
}

root = Path(__file__).resolve().parent.parent
pot = (root / "languages/pnscripts-waymend-lite.pot").read_text(encoding="utf-8")
header = (
    'msgid ""\nmsgstr ""\n'
    '"Project-Id-Version: Waymend 1.0.0\\n"\n'
    '"PO-Revision-Date: 2026-10-10 12:00+0300\\n"\n'
    '"Last-Translator: PN Scripts <office@pnscripts.com>\\n"\n'
    '"Language-Team: Bulgarian\\n"\n'
    '"Language: bg_BG\\n"\n'
    '"MIME-Version: 1.0\\n"\n'
    '"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '"Content-Transfer-Encoding: 8bit\\n"\n'
    '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n'
    '"X-Domain: pnscripts-waymend-lite\\n"\n'
)


def q(text):
    return text.replace('"', '\\"') if '\\"' not in text else text


out, missing = [header], []
for entry in pot.split("\n\n")[1:]:
    m = re.search(r'^msgid "(.*)"$', entry, re.M)
    if not m:
        continue
    key = m.group(1)
    if key not in BG:
        missing.append(key)
        continue
    value = BG[key]
    if isinstance(value, tuple):
        entry = re.sub(r'^msgstr\[0\] ""$', 'msgstr[0] "' + q(value[0]) + '"', entry.strip(), flags=re.M)
        entry = re.sub(r'^msgstr\[1\] ""$', 'msgstr[1] "' + q(value[1]) + '"', entry, flags=re.M)
    else:
        entry = re.sub(r'^msgstr ""$', lambda _m: 'msgstr "' + q(value) + '"', entry.strip(), flags=re.M)
    out.append(entry + "\n")
(root / "languages/pnscripts-waymend-lite-bg_BG.po").write_text("\n".join(out), encoding="utf-8")
print("missing:", missing)
