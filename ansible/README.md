# CDash
Note: This requires Ansible >= 1.9

Provisions and manages a machine running CDash with Apache/MySQL.

## Variables
|Name                       |Default         |Description                                                    |
|:--------------------------|:--------------:|:--------------------------------------------------------------|
|cdash_version              |master          |A git-friendly string of the version of CDash to checkout      |
|cdash_install_path         |/var/www/CDash  |Location for CDash to be checked out (no trailing slash)       |

### Notes
- Apache serves CDash out of `{{ cdash_install_path }}/public`

## Testing
To test CDash once the machine is provisioned, run CMake with the following variables

```
cmake -DCDASH_SERVER=localhost -DCDASH_DIR_NAME="" -DCDASH_USE_SELENIUM=false -DCDASH_DB_LOGIN=cdash -DCDASH_DB_PASS=cdash -DCDASH_USE_PROTRACTOR=false {{ cdash_install_path }}
make
ctest
```

