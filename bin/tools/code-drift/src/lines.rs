use std::collections::HashSet;

use crate::source::{Finding, PhpFile};

pub struct NameRules {
    pub layer_words: Vec<String>,
    pub families: Vec<String>,
    pub family_paths: Vec<String>,
}

pub fn repo_in_controller(file: &PhpFile) -> Vec<Finding> {
    if !file.path.contains("/Controller/") {
        return Vec::new();
    }
    let property = regex!(r"^\s+(private|protected|public).*\b[A-Za-z]+Repository\s+\$");
    let repository = regex!(r".*\b([A-Za-z]+Repository)\s+\$");

    let mut found = Vec::new();
    for (number, _, code) in file.lines() {
        if !property.is_match(code) {
            continue;
        }
        let Some(name) = repository.captures(code).map(|c| c[1].to_string()) else {
            continue;
        };
        found.push(Finding {
            detector: "repo-in-controller",
            path: file.path.clone(),
            row: format!("{}  {}  (line {})", file.path, name, number),
            symbol: Some(name),
        });
    }
    found
}

/// Class names declared in a way a `readonly` child could extend. A parent missing from this set
/// lives outside the tree (Exception, AbstractController, Command), and is never readonly.
pub fn declared_classes(files: &[PhpFile]) -> HashSet<String> {
    let declaration = regex!(r"^(final |abstract )*(readonly )*class (\w+)\b");
    files
        .iter()
        .flat_map(|file| file.code_lines())
        .filter_map(|line| declaration.captures(line).map(|c| c[3].to_string()))
        .collect()
}

pub fn mutable_service(file: &PhpFile, declared: &HashSet<String>) -> Option<Finding> {
    let path = &file.path;
    let in_scope = (path.contains("/Service/") || path.contains("/Security/"))
        && !path.contains("/Controller/")
        && !path.contains("/Command/");
    if !in_scope {
        return None;
    }

    let class = regex!(r"^(final )?class ");
    let readonly = regex!(r"^(final )?readonly class ");
    let memo = regex!(r"^\s+(private|protected|public)\s+[^(]*\$[a-zA-Z_]+\s*(=[^;]*)?;");
    let function = regex!(r"\bfunction\s");
    let sealed_constructor = regex!(r"private function __construct\(\) \{\}");
    let parent = regex!(r"^(final |abstract )*class \w+ extends (\w+)");

    let lines: Vec<&str> = file.code_lines().collect();
    if !lines.iter().any(|l| class.is_match(l)) || lines.iter().any(|l| readonly.is_match(l)) {
        return None;
    }
    if lines.iter().any(|l| memo.is_match(l)) {
        return None;
    }
    let has_behaviour = lines
        .iter()
        .any(|l| function.is_match(l) && !sealed_constructor.is_match(l));
    if !has_behaviour {
        return None;
    }
    let parent = lines
        .iter()
        .find_map(|l| parent.captures(l).map(|c| c[2].to_string()));
    if parent.is_some_and(|parent| !declared.contains(&parent)) {
        return None;
    }

    Some(Finding {
        detector: "mutable-service",
        path: path.clone(),
        symbol: None,
        row: path.clone(),
    })
}

pub fn fqcn(file: &PhpFile) -> Vec<Finding> {
    let inline = regex!(
        r"(new |catch \(|instanceof |: |\| )\\[A-Z][A-Za-z0-9_]*(\\[A-Z][A-Za-z0-9_]*)*\s*[(:{|;,)]"
    );
    file.lines()
        .filter(|(_, _, code)| inline.is_match(code))
        .map(|(number, raw, _)| Finding {
            detector: "fqcn",
            path: file.path.clone(),
            symbol: file.enclosing_symbol(number).map(str::to_string),
            row: format!("{}:{}:{}", file.path, number, raw),
        })
        .collect()
}

pub fn static_methods(file: &PhpFile) -> Vec<Finding> {
    let path = &file.path;
    if ["/ValueObject/", "/Dto/", "/DataFixtures/"]
        .iter()
        .any(|folder| path.contains(folder))
    {
        return Vec::new();
    }
    if file.code_lines().any(|l| regex!(r"^\s*enum ").is_match(l)) {
        return Vec::new();
    }

    let declaration = regex!(r"^\s+(public|private|protected)\s+static\s+function");
    let framework = regex!(
        r"getSubscribedEvents|getSubscribedServices|getDefaultName|getDefaultDescription|getGroups"
    );
    let injects = regex!(
        r"^\s+(private|protected)\s+(readonly\s+)?[A-Z]\w*(Interface|Service|Repository|Manager|Registry)\s+\$"
    );
    let named_constructor = regex!(
        r"public\s+static\s+function\s+\w+\([^)]*\)?\s*:\s*\??(self|static)\b|public\s+static\s+function\s+\w+\($"
    );
    let name = regex!(r"function\s+&?\s*(\w+)");

    let value_type = !file.code_lines().any(|l| injects.is_match(l));

    file.lines()
        .filter(|(_, _, code)| declaration.is_match(code) && !framework.is_match(code))
        .filter(|(_, _, code)| !(value_type && named_constructor.is_match(code)))
        .map(|(number, raw, code)| Finding {
            detector: "static",
            path: path.clone(),
            symbol: name.captures(code).map(|c| c[1].to_string()),
            row: format!("{}:{}:{}", path, number, raw),
        })
        .collect()
}

pub fn raw_sql(file: &PhpFile) -> Vec<Finding> {
    if !file.path.contains("/Repository/") {
        return Vec::new();
    }
    let native = regex!(
        r"createNativeQuery|->getConnection\(\)|executeQuery\(|executeStatement\(|->prepare\("
    );
    file.lines()
        .filter(|(_, _, code)| native.is_match(code))
        .map(|(number, raw, _)| Finding {
            detector: "raw-sql",
            path: file.path.clone(),
            symbol: file.enclosing_method(number).map(str::to_string),
            row: format!("{}:{}:{}", file.path, number, raw),
        })
        .collect()
}

const ROOT_SEGMENTS: [&str; 4] = ["src", "plugins", "modules", "App"];

/// Only a prefix or middle repeat counts: a trailing repeat is a conventional Symfony layer
/// suffix (`EventController`), which the standard names as an exception.
pub fn echo_name(file: &PhpFile, rules: &NameRules) -> Option<Finding> {
    let class = file.class_name();
    let path = &file.path;
    if rules
        .families
        .iter()
        .any(|family| class.ends_with(family.as_str()))
    {
        return None;
    }
    if rules
        .family_paths
        .iter()
        .any(|folder| path.starts_with(&format!("{}/", folder)))
    {
        return None;
    }

    let mut segments: Vec<&str> = path.split('/').collect();
    segments.pop();
    for segment in segments.into_iter().rev() {
        if ROOT_SEGMENTS.contains(&segment) {
            break;
        }
        let singular = segment.strip_suffix('s').unwrap_or(segment);
        for candidate in [segment, singular] {
            if candidate.chars().count() < 4 || class.ends_with(candidate) {
                continue;
            }
            if bare_layer_word(class, segment, candidate, &rules.layer_words) {
                continue;
            }
            if class == format!("Abstract{}Controller", segment)
                || class == format!("Abstract{}Controller", candidate)
            {
                continue;
            }
            if class.contains(candidate) && class != candidate {
                return Some(Finding {
                    detector: "echo-name",
                    path: path.clone(),
                    symbol: None,
                    row: format!("{:<70} repeats {}/", path, segment),
                });
            }
        }
    }
    None
}

/// The reduction must never leave a bare layer word: `Config/ConfigService` stays.
fn bare_layer_word(class: &str, segment: &str, candidate: &str, words: &[String]) -> bool {
    [segment, candidate].iter().any(|prefix| {
        class
            .strip_prefix(prefix)
            .is_some_and(|rest| words.iter().any(|word| word == rest))
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    fn php(path: &str, body: &str) -> PhpFile {
        PhpFile::new(path, format!("<?php\n\n{}", body))
    }

    fn rules() -> NameRules {
        NameRules {
            layer_words: vec!["Service".to_string(), "Interface".to_string()],
            families: vec!["ProviderInterface".to_string()],
            family_paths: vec!["src/Kit".to_string()],
        }
    }

    #[test]
    fn repo_in_controller_names_the_injected_repository() {
        let file = php(
            "src/Controller/ShelfController.php",
            "final class ShelfController\n{\n    public function __construct(\n        private readonly ShelfRepository $shelves,\n    ) {}\n}\n",
        );
        let found = repo_in_controller(&file);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].symbol.as_deref(), Some("ShelfRepository"));
        assert_eq!(
            found[0].row,
            "src/Controller/ShelfController.php  ShelfRepository  (line 6)"
        );
    }

    #[test]
    fn repo_in_controller_ignores_files_outside_controller_folders() {
        let file = php(
            "src/Service/ShelfService.php",
            "final readonly class ShelfService\n{\n    public function __construct(\n        private ShelfRepository $shelves,\n    ) {}\n}\n",
        );
        assert!(repo_in_controller(&file).is_empty());
    }

    #[test]
    fn mutable_service_flags_a_stateless_class_without_readonly() {
        let file = php(
            "src/Service/Clock.php",
            "final class Clock\n{\n    public function now(): int\n    {\n        return 1;\n    }\n}\n",
        );
        assert!(mutable_service(&file, &HashSet::new()).is_some());
    }

    #[test]
    fn mutable_service_accepts_a_memo_field() {
        let file = php(
            "src/Service/Clock.php",
            "final class Clock\n{\n    private ?int $cached = null;\n\n    public function now(): int\n    {\n        return 1;\n    }\n}\n",
        );
        assert!(mutable_service(&file, &HashSet::new()).is_none());
    }

    #[test]
    fn mutable_service_skips_a_constant_holder() {
        let file = php(
            "src/Service/Keys.php",
            "final class Keys\n{\n    public const A = 'a';\n\n    private function __construct() {}\n}\n",
        );
        assert!(mutable_service(&file, &HashSet::new()).is_none());
    }

    #[test]
    fn mutable_service_skips_a_child_of_a_parent_outside_the_tree() {
        let file = php(
            "src/Security/Gate.php",
            "final class Gate extends Voter\n{\n    public function vote(): int\n    {\n        return 1;\n    }\n}\n",
        );
        assert!(mutable_service(&file, &HashSet::new()).is_none());
        let declared: HashSet<String> = ["Voter".to_string()].into();
        assert!(mutable_service(&file, &declared).is_some());
    }

    #[test]
    fn mutable_service_ignores_commands_under_a_service_folder() {
        let file = php(
            "src/Service/Command/Run.php",
            "final class Run\n{\n    public function go(): void {}\n}\n",
        );
        assert!(mutable_service(&file, &HashSet::new()).is_none());
    }

    #[test]
    fn fqcn_flags_an_inline_class_name_and_names_the_method() {
        let file = php(
            "src/Service/Maker.php",
            "final readonly class Maker\n{\n    public function make(): object\n    {\n        return new \\Vendor\\Thing();\n    }\n}\n",
        );
        let found = fqcn(&file);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].symbol.as_deref(), Some("make"));
    }

    #[test]
    fn fqcn_ignores_class_names_in_strings_and_comments() {
        let file = php(
            "src/Service/Maker.php",
            "final readonly class Maker\n{\n    public function make(): string\n    {\n        // new \\Vendor\\Thing();\n        return 'new \\Vendor\\Thing()';\n    }\n}\n",
        );
        assert!(fqcn(&file).is_empty());
    }

    #[test]
    fn static_flags_a_static_method_on_a_service() {
        let file = php(
            "src/Service/Maths.php",
            "final readonly class Maths\n{\n    public function __construct(private CacheInterface $cache) {}\n\n    public static function twice(int $a): int\n    {\n        return $a * 2;\n    }\n}\n",
        );
        let found = static_methods(&file);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].symbol.as_deref(), Some("twice"));
    }

    #[test]
    fn static_skips_enums_value_objects_and_framework_hooks() {
        let enum_file = php(
            "src/Model/Kind.php",
            "enum Kind: string\n{\n    public static function all(): array { return []; }\n}\n",
        );
        let value_object = php(
            "src/ValueObject/Money.php",
            "final readonly class Money\n{\n    public static function zero(): array { return []; }\n}\n",
        );
        let subscriber = php(
            "src/Listener/Hook.php",
            "final class Hook\n{\n    public static function getSubscribedEvents(): array { return []; }\n}\n",
        );
        assert!(static_methods(&enum_file).is_empty());
        assert!(static_methods(&value_object).is_empty());
        assert!(static_methods(&subscriber).is_empty());
    }

    #[test]
    fn static_accepts_a_named_constructor_on_a_class_that_injects_nothing() {
        let file = php(
            "src/Model/Range.php",
            "final readonly class Range\n{\n    public static function fromPair(int $a, int $b): self\n    {\n        return new self();\n    }\n}\n",
        );
        assert!(static_methods(&file).is_empty());
    }

    #[test]
    fn static_flags_a_named_constructor_on_a_class_that_injects() {
        let file = php(
            "src/Model/Range.php",
            "final readonly class Range\n{\n    public function __construct(\n        private RangeRepository $ranges,\n    ) {}\n\n    public static function fromPair(int $a, int $b): self\n    {\n        return new self();\n    }\n}\n",
        );
        assert_eq!(static_methods(&file).len(), 1);
    }

    #[test]
    fn raw_sql_flags_native_queries_with_their_method() {
        let file = php(
            "src/Repository/RowRepository.php",
            "final class RowRepository\n{\n    public function insertRows(): void\n    {\n        $this->connection->executeStatement('INSERT IGNORE INTO rows VALUES (1)');\n    }\n}\n",
        );
        let found = raw_sql(&file);
        assert_eq!(found.len(), 1);
        assert_eq!(found[0].symbol.as_deref(), Some("insertRows"));
    }

    #[test]
    fn raw_sql_ignores_files_outside_repository_folders() {
        let file = php(
            "src/Service/Rows.php",
            "final class Rows\n{\n    public function go(): void\n    {\n        $this->connection->executeStatement('x');\n    }\n}\n",
        );
        assert!(raw_sql(&file).is_empty());
    }

    fn echo(path: &str) -> Option<Finding> {
        echo_name(&php(path, ""), &rules())
    }

    #[test]
    fn echo_name_flags_a_prefix_repeat() {
        let found = echo("src/Shelf/ShelfEntryBuilder.php").unwrap();
        assert!(found.row.ends_with("repeats Shelf/"));
    }

    #[test]
    fn echo_name_counts_the_singular_of_a_plural_folder() {
        assert!(echo("src/Shelves/Items/ItemCounter.php").is_some());
    }

    #[test]
    fn echo_name_accepts_a_trailing_repeat() {
        assert!(echo("src/Shelf/OpenShelf.php").is_none());
    }

    #[test]
    fn echo_name_keeps_a_bare_layer_word() {
        assert!(echo("src/Config/ConfigService.php").is_none());
    }

    #[test]
    fn echo_name_keeps_the_abstract_folder_controller() {
        assert!(echo("src/Controller/Admin/AbstractAdminController.php").is_none());
    }

    #[test]
    fn echo_name_stops_at_the_source_root() {
        assert!(echo("plugins/shelf/src/ShelfThing.php").is_none());
    }

    #[test]
    fn echo_name_skips_short_folder_names() {
        assert!(echo("src/Api/ApiClient.php").is_none());
    }

    #[test]
    fn echo_name_skips_a_name_family() {
        assert!(echo("src/Shelf/ShelfProviderInterface.php").is_none());
    }

    #[test]
    fn echo_name_skips_a_family_folder() {
        assert!(echo("src/Kit/Tabs/KitTab.php").is_none());
    }
}
