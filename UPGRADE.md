# Upgrade guide

## From 1.x to 2.0

### Customizable columns

#### `CustomizableColumnsConfigurationInterface` methods are no longer static

`getLockedColumns()` and `getDefaultColumns()` are now instance methods. The bundle calls them on the CrudController instance, so they can depend on the current user or request.

Remove the `static` keyword from both methods in every controller implementing the interface:

```diff
 final class BookCrudController extends AbstractCrudController implements CustomizableColumnsConfigurationInterface
 {
-    public static function getLockedColumns(): array
+    public function getLockedColumns(): array
     {
         return ['title'];
     }

-    public static function getDefaultColumns(): array
+    public function getDefaultColumns(): array
     {
         return ['title', 'author'];
     }
 }
```

Calls like `BookCrudController::getDefaultColumns()` in your own code no longer work. Call the method on a controller instance instead.

#### `CustomizableColumnsFieldsFilter::filter()` takes the controller instance

The second argument used to be the CrudController class name. It is now the controller object:

```diff
 public function configureFields(string $pageName): iterable
 {
     $fields = [/* every candidate column */];

-    return Crud::PAGE_INDEX === $pageName ? $this->fieldsFilter->filter($fields, self::class) : $fields;
+    return Crud::PAGE_INDEX === $pageName ? $this->fieldsFilter->filter($fields, $this) : $fields;
 }
```
