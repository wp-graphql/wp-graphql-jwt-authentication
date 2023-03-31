# Changelog

## [v0.7.0](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.7.0) (2023-03-31)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.6.0...v0.7.0)

**Other Changes:**

- fix: php-jwt version fixed. [\#172](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/172) ([kidunot89](https://github.com/kidunot89))
- Remove codecept\_debug which causes internal server error [\#159](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/159) ([paolospag](https://github.com/paolospag))
- Build with composer --no-dev [\#155](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/155) ([markkelnar](https://github.com/markkelnar))
- Allow multiple iss domains [\#141](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/141) ([fjobeir](https://github.com/fjobeir))
- Set a 400+ status when throwing WP\_Errors  [\#137](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/137) ([pcraciunoiu](https://github.com/pcraciunoiu))
- Added the action graphql\_jwt\_auth\_before\_authenticate and the filter … [\#135](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/135) ([spiralni](https://github.com/spiralni))
- Fix incorrect error message on invalid secret [\#126](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/126) ([markspolakovs](https://github.com/markspolakovs))
- fix\(response headers\): replace single header instead of overwriting them all [\#118](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/118) ([tsmith-rv](https://github.com/tsmith-rv))
- \#87 - Force Auth Secret to be set, else throw Exception [\#88](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/88) ([jasonbahl](https://github.com/jasonbahl))
- Adds Option to define if a cookie should be set on login. [\#85](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/85) ([henrikwirth](https://github.com/henrikwirth))
- Update php-jwt to latest version [\#84](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/84) ([efoken](https://github.com/efoken))
- Fix Auth Expiration time filter and add it to documentation [\#83](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/83) ([henrikwirth](https://github.com/henrikwirth))

## [v0.6.0](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.6.0) (2022-10-25)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.5.2...v0.6.0)

## [v0.5.2](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.5.2) (2022-05-16)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.5.1...v0.5.2)

## [v0.5.1](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.5.1) (2022-04-22)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.5.0...v0.5.1)

## [v0.5.0](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.5.0) (2022-04-15)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.4.1...v0.5.0)

## [v0.4.1](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.4.1) (2020-05-04)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.4.0...v0.4.1)

## [v0.4.0](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.4.0) (2020-02-20)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.3.5...v0.4.0)

**Fixed:**

- \#69 - Backward Compatibility fixes for playing nice with WPGraphQL for WooCommerce [\#75](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/75) ([jasonbahl](https://github.com/jasonbahl))
- \#69 - JWT Tokens could not be returned after regiserUser mutation [\#72](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/72) ([jasonbahl](https://github.com/jasonbahl))

**Other Changes:**

- Release/v0.4.0 [\#81](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/81) ([jasonbahl](https://github.com/jasonbahl))
- Docs/add all mutations [\#78](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/78) ([henrikwirth](https://github.com/henrikwirth))
- Bug/\#69 jwt could not be returned after user registered [\#74](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/74) ([jasonbahl](https://github.com/jasonbahl))
- \#45 - auth and refresh token are same for non-admins [\#64](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/64) ([jasonbahl](https://github.com/jasonbahl))

## [v0.3.5](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.3.5) (2020-01-29)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.3.4...v0.3.5)

**Other Changes:**

- release/v0.3.5 [\#68](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/68) ([jasonbahl](https://github.com/jasonbahl))
- - WPGraphQL v0.6.0 regression [\#66](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/66) ([jasonbahl](https://github.com/jasonbahl))

## [v0.3.4](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.3.4) (2020-01-21)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/v0.3.3...v0.3.4)

**Other Changes:**

- release/v0.3.4 - Update version [\#63](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/63) ([jasonbahl](https://github.com/jasonbahl))
- JWT field registration updated. [\#62](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/62) ([kidunot89](https://github.com/kidunot89))
- Remove type-hinting from "Auth::get\_signed\_token" [\#61](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/61) ([kidunot89](https://github.com/kidunot89))
- Revert "Revert "Adds WPGraphQL v0.6.0 support"" [\#60](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/60) ([jasonbahl](https://github.com/jasonbahl))
- Adds user ID to LoginPayload [\#57](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/57) ([kidunot89](https://github.com/kidunot89))
- Revert "Adds WPGraphQL v0.6.0 support" [\#56](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/56) ([jasonbahl](https://github.com/jasonbahl))
- Adds WPGraphQL v0.6.0 support [\#55](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/55) ([kidunot89](https://github.com/kidunot89))
- Correct a couple broken links in readme [\#54](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/54) ([kellenmace](https://github.com/kellenmace))
- - Update travis.yml config [\#52](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/52) ([jasonbahl](https://github.com/jasonbahl))

## [v0.3.3](https://github.com/wp-graphql/wp-graphql-jwt-authentication/tree/v0.3.3) (2019-10-18)

[Full Changelog](https://github.com/wp-graphql/wp-graphql-jwt-authentication/compare/V0.3.2...v0.3.3)

**New Features:**

- \#49 - use register\_graphql\_mutation [\#50](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/50) ([jasonbahl](https://github.com/jasonbahl))

**Fixed:**

- \#41 - JWT fields cannot be retrieved via viewer query [\#42](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/42) ([jasonbahl](https://github.com/jasonbahl))
- Conflicts with "The Events Calendar" also active [\#40](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/40) ([jasonbahl](https://github.com/jasonbahl))

**Other Changes:**

- Fixes Type hinting [\#47](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/47) ([kidunot89](https://github.com/kidunot89))
- Removed vendor from .distignore [\#46](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/46) ([igoojoe](https://github.com/igoojoe))
- BUGFIX: $auth\_header always setted [\#37](https://github.com/wp-graphql/wp-graphql-jwt-authentication/pull/37) ([OnekO](https://github.com/OnekO))



\* *This Changelog was automatically generated by [github_changelog_generator](https://github.com/github-changelog-generator/github-changelog-generator)*
