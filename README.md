# PHP File Processor

## Overview

`process_files.php` is a powerful PHP script designed to automate the process of updating PHP files from version 5 to 8. It performs three main functions: backing up original files, processing and updating PHP syntax, and logging all changes and actions.

## Features

1. **Backup**: Creates timestamped backups of all processed files.
2. **Process**: Updates PHP syntax and code constructs to be compatible with PHP 8.
3. **Log**: Generates detailed logs of all actions and changes made to files.

## How It Works

### Backup

- Creates a backup directory with a timestamp: `{directory}_backup_{timestamp}`.
- Copies all PHP files to the backup directory before processing.

### Process

The script processes PHP files by making the following updates:

- Removes parentheses from `require`, `include`, `require_once`, and `include_once` statements.
- Updates variable syntax in include/require statements for PHP 8.3 compatibility.
- Ensures proper spacing in include/require statements.
- Converts `TRUE` and `FALSE` to lowercase `true` and `false`.
- Replaces `else if` with `elseif`.
- Converts `and`/`AND` to `&&` and `or`/`OR` to `||` within `if` statements.
- Removes trailing whitespace from each line.

### Log

- Creates a log file: `{directory}_processing_log_{timestamp}.txt`.
- Logs all actions: backups, processed files, skipped files, and errors.
- Records specific changes made to each file.

## Usage

1. Set the `$dir` and `$path` variables at the bottom of the script:

   ```php
   $dir = "your_directory_name";
   $path = "your_path/{$dir}";
   ```

2. Run the script:

    ```code
    php process_files.php
    ```

3. Check the console output and log file for results.

## Output

The script provides both console output and a detailed log file, including:

- Total files backed up
- Total files processed
- Number of empty files skipped
- Number of errors encountered
- Detailed changes made to each file

## Requirements

- PHP 8.0 or higher
- Write permissions in the target directory

## Note

Always review the changes made by the script and test your code thoroughly after processing.

## License

This project is open-source and available under the MIT License.
