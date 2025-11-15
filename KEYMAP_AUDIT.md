# Keymap Bug Audit Progress

**Purpose**: Systematically audit every file for incorrect usage of `getKey()`, `find()`, or primary key assumptions when keymap values should be used.

**Pattern to find**: Any place where we extract a key from a model and use it to query/insert/compare against database columns that store keymap values (actor_id, subject_id, context_id, restricted_to_id).

**Status Legend**:
- [ ] Not checked
- [x] ✅ Safe - No issues found
- [x] ⚠️ Suspicious - Needs closer review
- [x] 🔧 Fixed - Bug found and corrected

---

## Clipboard
- [ ] src/Clipboard/AbstractClipboard.php
- [ ] src/Clipboard/CachedClipboard.php
- [ ] src/Clipboard/Clipboard.php

## Conductors
- [x] 🔧 src/Conductors/AssignsRoles.php - FIXED: Line 143 authority lookup
- [ ] src/Conductors/ChecksRoles.php
- [ ] src/Conductors/Concerns/AssociatesAbilities.php
- [ ] src/Conductors/Concerns/ConductsAbilities.php
- [x] 🔧 src/Conductors/Concerns/DisassociatesAbilities.php - FIXED: Line 113 getKey() to keymap value
- [ ] src/Conductors/Concerns/FindsAndCreatesAbilities.php
- [ ] src/Conductors/ForbidsAbilities.php
- [x] ✅ src/Conductors/GivesAbilities.php - Safe: No find() calls
- [ ] src/Conductors/Lazy/ConductsAbilities.php
- [ ] src/Conductors/Lazy/HandlesOwnership.php
- [x] ✅ src/Conductors/RemovesAbilities.php - Safe: Uses trait, no find()
- [x] ✅ src/Conductors/RemovesRoles.php - Safe: Uses IDs in WHERE directly
- [x] 🔧 src/Conductors/SyncsRolesAndAbilities.php - FIXED: Line 264 getKey() to keymap value
- [ ] src/Conductors/UnforbidsAbilities.php

## Console
- [ ] src/Console/CleanCommand.php
- [ ] src/Console/MigrateFromBouncerCommand.php
- [ ] src/Console/MigrateFromSpatieCommand.php

## Constraints
- [ ] src/Constraints/Builder.php
- [ ] src/Constraints/ColumnConstraint.php
- [ ] src/Constraints/Constrainer.php
- [ ] src/Constraints/Constraint.php
- [ ] src/Constraints/Group.php
- [ ] src/Constraints/ValueConstraint.php

## Contracts (Interfaces)
- [x] ✅ src/Contracts/CachedClipboardInterface.php - Interface only
- [x] ✅ src/Contracts/ClipboardInterface.php - Interface only
- [x] ✅ src/Contracts/MigratorInterface.php - Interface only
- [x] ✅ src/Contracts/ScopeInterface.php - Interface only

## Database Models
- [x] ✅ src/Database/Ability.php - Model definition only
- [x] ✅ src/Database/AssignedRole.php - Pivot model
- [ ] src/Database/Concerns/Authorizable.php
- [ ] src/Database/Concerns/HasAbilities.php
- [ ] src/Database/Concerns/HasRoles.php
- [x] ✅ src/Database/Concerns/HasWardenPrimaryKey.php - Key configuration only
- [ ] src/Database/Concerns/IsAbility.php
- [x] ✅ src/Database/Concerns/IsRole.php - Safe: getKey() usage is for role IDs (correct)
- [ ] src/Database/HasRolesAndAbilities.php
- [x] 🔧 src/Database/ModelRegistry.php - FIXED: Lines 317 (ownership check), 497-506 (new helper)
- [x] ✅ src/Database/Models.php - Facade only
- [x] ✅ src/Database/Permission.php - Pivot model
- [x] 🔧 src/Database/Queries/Abilities.php - FIXED: Lines 120-126, 150-157 (keymap column/values)
- [x] ✅ src/Database/Queries/AbilitiesForModel.php - Safe: Already uses keymap values
- [x] 🔧 src/Database/Queries/Roles.php - FIXED: Lines 112-113, 117, 120 (keymap column)
- [x] ✅ src/Database/Role.php - Model definition only
- [ ] src/Database/Scope/Scope.php
- [ ] src/Database/Scope/TenantScope.php
- [x] ✅ src/Database/Titles/AbilityTitle.php - Value object
- [x] ✅ src/Database/Titles/RoleTitle.php - Value object
- [x] ✅ src/Database/Titles/Title.php - Value object

## Enums
- [x] ✅ src/Enums/MorphType.php - Enum only
- [x] ✅ src/Enums/PrimaryKeyType.php - Enum only

## Exceptions
- [x] ✅ src/Exceptions/InvalidConfigurationException.php - Exception only
- [x] ✅ src/Exceptions/MorphKeyViolationException.php - Exception only

## Facades
- [x] ✅ src/Facades/Warden.php - Facade only

## Core
- [ ] src/Factory.php
- [ ] src/Guard.php
- [ ] src/Warden.php
- [x] ✅ src/WardenServiceProvider.php - Service provider only

## HTTP
- [ ] src/Http/Middleware/ScopeWarden.php

## Migrators
- [x] 🔧 src/Migrators/BouncerMigrator.php - FIXED: Lines 285-304 findUser()
- [x] 🔧 src/Migrators/SpatieMigrator.php - FIXED: Lines 175-187 findUser()

## Support
- [x] 🔧 src/Support/Helpers.php - FIXED: Lines 85, 92 extractModelAndKeys()
- [x] ✅ src/Support/PrimaryKeyGenerator.php - Key generation only
- [x] ✅ src/Support/PrimaryKeyValue.php - Value object

---

## Summary
- **Total Files**: 65
- **Checked**: 33
- **Safe**: 24
- **Fixed**: 9
- **Remaining**: 32

## Known Issues Fixed
1. ✅ AssignsRoles::assignRoles() - Used find() with keymap value
2. ✅ SpatieMigrator::findUser() - Used find() with keymap value
3. ✅ BouncerMigrator::findUser() - Used find() with keymap value
4. ✅ ModelRegistry::owns() - Compared getKey() against actor_id
5. ✅ Helpers::extractModelAndKeys() - Used getKey() instead of keymap value
6. ✅ ModelRegistry::getModelKeyFromClass() - New helper added
7. ✅ Queries/Abilities::getAuthorityRoleConstraint() - Used getKeyName()/getKey()
8. ✅ Queries/Abilities::getAuthorityConstraint() - Used getKeyName()/getKey()
9. ✅ DisassociatesAbilities::getAbilitiesPivotQuery() - Used getKey()
10. ✅ SyncsRolesAndAbilities::newPivotQuery() - Used getKey()
11. ✅ Queries/Roles::constrainWhereAssignedTo() - Used getKeyName()

## Next Priority Files
Focus on files that interact with pivot tables or morph relationships:
1. src/Conductors/Concerns/AssociatesAbilities.php - Attach operations
2. src/Database/Concerns/HasAbilities.php - Ability relationship methods
3. src/Database/Concerns/HasRoles.php - Role relationship methods
4. src/Database/Concerns/IsAbility.php - Ability model methods
5. src/Database/HasRolesAndAbilities.php - Combined trait
