# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.3.0 - 2016-07-13

### Added

- [zfcampus/zf-content-validation#64](https://github.com/zfcampus/zf-content-validation/pull/64) adds support
  for providing input filters for `GET` requests; these will validate query
  parameters. Configuration is exactly as it is for other HTTP methods.
- [zfcampus/zf-content-validation#66](https://github.com/zfcampus/zf-content-validation/pull/66) and
  [zfcampus/zf-content-validation#67](https://github.com/zfcampus/zf-content-validation/pull/67) add support
  for v3 releases of Laminas components, while retaining backwards
  compatibility with v2 releases.

### Deprecated

- Nothing.

### Removed

- [zfcampus/zf-content-validation#67](https://github.com/zfcampus/zf-content-validation/pull/67) removes
  support for PHP 5.5.

### Fixed

- [zfcampus/zf-content-validation#65](https://github.com/zfcampus/zf-content-validation/pull/65) adds the
  ability to specify the flags `allows_only_fields_in_filter` and `use_raw_data`
  in combination to ensure that raw data will not contain any keys not defined
  in the input filters. (Previously, `allows_only_fields_in_filter` was ignored
  when `use_raw_data` was specified.)

## 1.2.1 - 2016-07-12

### Added

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- [zfcampus/zf-content-validation#63](https://github.com/zfcampus/zf-content-validation/pull/63) provides a
  fix to ensure that numeric keys in validation collections are preserved when
  merging file data into the set.
