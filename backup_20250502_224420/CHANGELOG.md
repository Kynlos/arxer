# Arxer Changelog

## [Unreleased] - 2024-05-02

### Added
- Support for Gemma 3 4B-IT-QAT model as a local provider option
- Model selection persistence across browser sessions
- Visual indicator when viewing past chat sessions

### Fixed
- Chat history now groups related conversations properly
- Multiple interactions within 30 minutes are now shown as a single conversation
- Fixed chat history restore functionality
- Added validation to prevent errors with invalid chat data
- Added localStorage availability check with user warning
- Improved debugging logs throughout the application

### Changed
- Chat sessions now retain timestamp of last interaction
- Updated history display to show most recent sessions first
- Improved error handling for chat history storage
- Enhanced session restore with better visual indicators