# Script to backup files, push to master and merge with main

# Check if git is installed
if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-Host "Error: Git is not installed or not in PATH" -ForegroundColor Red
    exit 1
}

# Create backup directory
Write-Host "Creating backup..." -ForegroundColor Cyan
$backupDir = "./backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
New-Item -ItemType Directory -Path $backupDir -Force | Out-Null

# Get all files to backup (excluding .git directory)
Get-ChildItem -File | ForEach-Object {
    Copy-Item $_.FullName -Destination "$backupDir/$($_.Name)" -Force
    Write-Host "Backed up: $($_.Name)" -ForegroundColor Gray
}

Write-Host "Backup complete at $backupDir" -ForegroundColor Green

# Make sure we're on master branch
$currentBranch = git branch --show-current
if ($currentBranch -ne "master") {
    Write-Host "Switching to master branch..." -ForegroundColor Cyan
    git checkout master
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Error: Failed to switch to master branch" -ForegroundColor Red
        exit 1
    }
}

# Check if there are any changes to commit
$status = git status --porcelain
if ($status) {
    Write-Host "Committing changes to master..." -ForegroundColor Cyan
    git add .
    git commit -m "Update files before merging to main"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Error: Failed to commit changes" -ForegroundColor Red
        exit 1
    }
}

# Push master branch
Write-Host "Pushing to master branch..." -ForegroundColor Cyan
git push -u origin master
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to push to master branch" -ForegroundColor Red
    exit 1
}

# Try to checkout main branch
Write-Host "Checking out main branch..." -ForegroundColor Cyan
git fetch origin main
$mainExists = git show-ref --verify --quiet refs/remotes/origin/main

if ($LASTEXITCODE -eq 0) {
    # Main branch exists remotely
    git checkout -b main origin/main
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Attempting to create main branch locally..." -ForegroundColor Yellow
        git checkout -b main
    }
} else {
    Write-Host "Main branch doesn't exist remotely, creating locally..." -ForegroundColor Yellow
    git checkout -b main
}

# Merge master into main
Write-Host "Merging master into main..." -ForegroundColor Cyan
git merge master --allow-unrelated-histories -m "Merge master branch into main"
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Merge conflicts occurred. Resolve conflicts manually, then run 'git add .' and 'git commit'" -ForegroundColor Red
    exit 1
}

# Push main branch
Write-Host "Pushing main branch..." -ForegroundColor Cyan
git push -u origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to push to main branch" -ForegroundColor Red
    exit 1
}

Write-Host "Successfully pushed to master and merged with main!" -ForegroundColor Green
Write-Host "Backup of original files is available at: $backupDir" -ForegroundColor Green