# Troubleshooting

Common problems when working on the core and how to fix them.

---

## Service not autowired

**Symptom:** `Cannot autowire service "App\Service\MyService"` or the service simply isn't
injected.

**Causes and fixes:**

1. **Namespace mismatch** — the class namespace must start with `App\`:
   ```php
   // ✅ Correct
   namespace App\Service;

   // ❌ Wrong — won't be autowired
   namespace MyService;
   ```

2. **Excluded in config** — check `config/services.yaml` for any `exclude:` patterns that
   cover your file path. Services in excluded directories must be registered manually.

3. **Stale cache** — clear it:
   ```bash
   just app cache:clear
   ```

---

## Route not found

**Symptom:** `No route found for "GET /my-path"` or a 404 with no error detail.

**Fixes:**

1. Verify the `#[Route]` attribute is correct on the controller method:
   ```php
   #[Route('/my-path', name: 'app_my_route')]
   public function myAction(): Response { }
   ```

2. List all registered routes and search:
   ```bash
   just app debug:router | grep my_route
   ```

3. Clear the route cache:
   ```bash
   just app cache:clear
   ```

---

## Doctrine mapping error

**Symptom:** `Mapping exception`, `Class ... does not exist`, or entity not persisted.

**Fixes:**

1. Entity must be in `src/Entity/` with namespace `App\Entity\`:
   ```php
   namespace App\Entity;

   #[ORM\Entity(repositoryClass: MyEntityRepository::class)]
   class MyEntity { }
   ```

2. Check attribute syntax — use `#[ORM\Column]`, not `@ORM\Column` annotations.

3. Validate the schema:
   ```bash
   just app doctrine:schema:validate
   ```

4. Generate and review a migration:
   ```bash
   just app doctrine:migrations:diff
   ```

---

## N+1 query in a list page

**Symptom:** Page works but is slow; Symfony Profiler shows 50+ nearly identical queries.

**Diagnosis:**
Open the Symfony Profiler (the debug toolbar at the bottom of the page in dev mode) →
Database tab. Look for repeated `SELECT … WHERE id = ?` queries.

**Fix:** Add `leftJoin` + `addSelect` in the repository method that powers the list:

```php
// Before — lazy loads location for every row
public function findAll(): array
{
    return $this->createQueryBuilder('e')->getQuery()->getResult();
}

// After — one query with JOIN
public function findAllWithLocation(): array
{
    return $this->createQueryBuilder('e')
        ->leftJoin('e.location', 'l')
        ->addSelect('l')
        ->getQuery()
        ->getResult();
}
```

---

## Fixture reference not found

**Symptom:** `RuntimeException: Error retrieving reference 'Event::some-name'`

**Causes:**

1. **Wrong reference name** — use the fixture class constants:
   ```php
   // ✅ Use the constant
   $event = $this->getRefEvent(EventFixture::WEEKLY_GO_STUDY);

   // ❌ Typo-prone string
   $event = $this->getRefEvent('Weekly Go Study Group');
   ```

2. **Load order** — the fixture that calls `addRefXxx()` must run *before* the fixture that
   calls `getRefXxx()`. Declare dependencies:
   ```php
   public function getDependencies(): array
   {
       return [EventFixture::class];
   }
   ```

3. **Wrong group** — ensure both fixtures are in the same group (or the dependency's group
   is a subset of the dependent's group).

---

## Template variable undefined

**Symptom:** `Variable "myVar" does not exist` in Twig strict mode.

**Fixes:**

1. Ensure the controller passes the variable to the template:
   ```php
   return $this->render('my/template.html.twig', [
       'myVar' => $value,   // ← must be here
   ]);
   ```

2. Debug in the template:
   ```twig
   {{ dump(myVar) }}
   ```

3. If the variable is optional, use `default`:
   ```twig
   {{ myVar|default(null) }}
   ```

---

## Translation key missing

**Symptom:** The raw key string (e.g. `event.title.label`) appears on the page instead of
the translated text.

**Fix:**

1. Add the key to `translations/messages.en.yaml`
2. Run the extraction command to sync:
   ```bash
   just translationExtract
   ```
3. Fill missing DE/CN translations:
   ```
   /fill-translations
   ```

---

## Cache stale after config change

**Symptom:** A config change or new service registration isn't being picked up.

```bash
just app cache:clear
```

In dev mode, the cache auto-refreshes on most file changes, but config/container changes
sometimes require a manual clear.
