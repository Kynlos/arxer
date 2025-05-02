<?php
/**
 * Script to push changes to GitHub
 * 
 * This script automates the process of committing and pushing changes to GitHub.
 * Run this script with PHP CLI: php push_to_github.php
 */

// Configuration
$branch = 'main';                    // Target branch to push to
$commitMessage = 'Fixed UI issues and improved styling';

// Output styling
function printHeader($text) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo $text . "\n";
    echo str_repeat("=", 80) . "\n";
}

function printStep($text) {
    echo "\n[*] $text\n";
}

function printCommand($cmd) {
    echo "  > $cmd\n";
}

function runCommand($cmd) {
    printCommand($cmd);
    
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);
    
    foreach ($output as $line) {
        echo "    $line\n";
    }
    
    return $returnCode === 0;
}

printHeader("GitHub Push Script for Arxer Project");

// Check if git is installed
printStep("Checking git installation...");
if (!runCommand("git --version")) {
    echo "\nERROR: Git is not installed or not in the PATH. Please install git and try again.\n";
    exit(1);
}

// Check current status
printStep("Checking current status...");
runCommand("git status");

// Add all files
printStep("Adding files to git...");
runCommand("git add .");

// Commit changes
printStep("Committing changes...");
runCommand("git commit -m \"$commitMessage\"");

// Make sure we're on the right branch
printStep("Checking current branch...");
runCommand("git branch");

printStep("Switching to $branch branch if needed...");
runCommand("git checkout $branch");

// Push to GitHub
printStep("Pushing to GitHub...");
if (runCommand("git push origin $branch")) {
    printHeader("SUCCESS: Changes have been pushed to GitHub");
} else {
    printHeader("ERROR: Failed to push changes to GitHub");
    echo "Please check the error messages above and fix any issues before trying again.\n";
    echo "You might need to pull changes from remote first with: git pull origin $branch\n";
}

echo "\n";