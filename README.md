# php-slim-validator
Simple lightweight form(s) validator

Under **heavy** development.

Subclass class `Validator` and override `fetch()` method to get the input from `$_POST` or wherever you need (current default implementation use `$_REQUEST` which is probably a bad idea).

To add a new validator/rule subclass and add a new method (see the class `ValidatorBuiltIn` for validators format, they must return `true` or `false` only), also to add a new validator **must** override `map_fields()` and call the *parent/super* before return, in order to let the validator know how to handle input arguments and eventually how to display the fields/values in error messages (see `map_fields()` base implementation).

Instantiate the class `Validator`, use *json* with `json_decode()` to input *rules* and *messages* with method `validate()`

The result will be an object `ValidatorErrors` with errors in the array `->forms`.


TODO:
- documentation
