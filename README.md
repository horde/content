# Content

Content is the Horde tagging engine. It stores tags, references to the objects they are
applied to and keys for the users who applied them. Any Horde application can tag
arbitrary objects (contacts, events, tasks, bookmarks, etc.) through a shared
vocabulary. This enables cross-application tag searches and tag clouds.

Core classes:

- **`Content_Tagger`** -- The main API for tagging, untagging, searching, and
  building tag clouds.
- **`Content_Types_Manager`** / **`Content_Objects_Manager`** /
  **`Content_Users_Manager`** -- manage the type, object, and user registries
  that `Content_Tagger` depends on.

## Application integration

Horde applications do not call the HTTP endpoints directly. Instead each app
defines a thin wrapper that extends `Horde_Core_Tagger` and declares its app
name and supported object types:

```php
class Nag_Tagger extends Horde_Core_Tagger
{
    protected $_app = 'nag';
    protected $_types = ['task'];
}
```

`Horde_Core_Tagger` obtains a `Content_Tagger` instance via the injector and
delegates the heavy lifting. Apps that follow this pattern include **Kronolith**
(events), **Turba** (contacts), **Nag** (tasks), **Mnemo** (notes), **Trean**
(bookmarks), **Ansel** (photos/galleries), and **Jonah** (stories).

The most commonly used operations are `tag()`, `untag()`, `replaceTags()`,
`getTags()`, `getObjects()`, and `getTagCloud()`.

## HTTP endpoints

Content also exposes a set of PSR-15 request handlers mounted under the
application's web root. All endpoints currently require a Horde session (default
middleware stack).

PLANNED FEATURE: Allow making some endpoints public by opt-in.

### GET /tags

Search or list tags. Returns all tags when called without parameters.

| Parameter  | Description                                  |
|------------|----------------------------------------------|
| `q`        | Starts-with search on tag text               |
| `typeId`   | Restrict to tags on objects of this type      |
| `userId`   | Restrict to tags applied by this user         |
| `objectId` | Restrict to tags on this object               |
| `limit`    | Maximum number of results                     |
| `offset`   | Result offset for pagination                  |
| `format`   | `json` (default), `html`, `atom`, or `rss`   |

### GET /tags/recent

Most recently used tags.

| Parameter | Description                                   |
|-----------|-----------------------------------------------|
| `limit`   | Maximum number of tags (default 10)           |
| `offset`  | Result offset for pagination                  |
| `typeId`  | Restrict to tags on objects of this type       |
| `userId`  | Restrict to tags applied by this user          |
| `format`  | `json` (default), `html`, `atom`, or `rss`   |

### POST /tag

Tag an object. Accepts `POST` or `PUT`.

| Parameter  | Required | Description              |
|------------|----------|--------------------------|
| `userId`   | yes      | User applying the tag    |
| `objectId` | yes      | Object identifier        |
| `tags`     | yes      | Tag name(s) to apply     |
| `typeId`   | yes      | Object type              |

Returns `204 No Content` on success.

### POST /untag

Remove a tag from an object. Accepts `POST` or `DELETE`.

Same parameters as `/tag`. Returns `204 No Content` on success.
