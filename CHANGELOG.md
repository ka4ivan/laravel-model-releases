# Changelog

## 2.1.0 - 2025-04-15
- New method: isPrereleaseOrNew
- Update deleteWithReleases
- Update changelog (return collection)

## 2.0.6 - 2025-04-14
- Fix bugs

## 2.0.5 - 2025-04-13
- Fix bugs

## 2.0.4 - 2025-04-12
- Method visibility changed from private to protected

## 2.0.3 - 2025-04-11
- Added a new parameter $relationsData to the deleteWithReleases method
- Added a new method: isNew

## 2.0.2 - 2025-03-16
- Update helpers

## 2.0.1 - 2025-03-02
- Update README.md

## 2.0.0 - 2025-03-02
- Release switching
- Release hierarchy
- New helper `build_release_tree`
- New relationship `postrelease`

## 1.2.5 - 2025-02-23
- Fix bug

## 1.2.4 - 2025-02-21
- Fix bug

## 1.2.3 - 2025-02-20
- Add user to Release

## 1.2.2 - 2025-02-17
- Add clearing of all prerelease data

## 1.2.1 - 2025-02-16
- Update README.md

## 1.2.0 - 2025-02-16
- Add clean outdated release data command - `php artisan release:clean`
- Add new field to `releases` table - `cleaned_at`

## 1.1.2 - 2025-02-15
- Add custom fields to release changelog

## 1.1.1 - 2025-02-15
- Update README.md

## 1.1.0 - 2025-02-15
- Add release and model release changelog
- Add new field to models - `release_data`

## 1.0.2 - 2025-02-13
- Update README.md

## 1.0.1 - 2025-02-12
- Update README.md

## 1.0.0 - 2025-02-11
- Initial release
