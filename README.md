# Arxer - AI-Powered ArXiv Research Assistant

Arxer is a comprehensive research tool that combines ArXiv paper browsing with AI-powered assistance for academics, researchers, and students. It helps users discover, understand, and manage scientific papers with advanced search capabilities and AI explanations.

![Main](https://github.com/user-attachments/assets/fc85a18a-4b4a-41f2-9e92-ba6e259d7951)

## Features

### Paper Search and Discovery
- **Advanced ArXiv Search**: Search papers by title, author, abstract, or full text
- **Category Filtering**: Browse papers by specific ArXiv categories
- **Date Filtering**: Filter papers by publication date with syntax like `date>2020` or `date<2022`
- **Quote Support**: Search for exact phrases using quotation marks
- **Sort Options**: Sort papers by relevance, date, or citation count
- **Mobile-Responsive Interface**: Access on any device

### AI-Powered Features
- **Research Chat**: Ask questions about papers and get AI-generated answers
- **AI Tutor**: Learn concepts through guided Socratic dialogue rather than just getting answers
- **Paper Explainer**: Generate detailed explanations of papers including:
  - Key concepts breakdown
  - Methodology explanation
  - Research significance
  - Related areas identification
- **Paper Recommendations**: Get AI-suggested similar papers based on your interests
- **Knowledge Graph**: Visualize relationships between papers and research concepts
- **LaTeX Support**: Render mathematical equations in explanations

### Content Management
- **Favorites System**: Save papers for later reading
- **Collections**: Organize papers into custom collections
- **Citation Management**: Format citations in various academic styles
- **Reading History**: Track which papers you've viewed

### AI Provider Options
- **Local LM Studio**: Use local LLMs for privacy and offline access
- **OpenAI Integration**: Leverage OpenAI models for powerful analysis
- **Configurable Parameters**: Adjust model, temperature, and token settings

## Configuration

### AI Provider Configuration
The system supports multiple AI providers with customizable settings in `ai_config.json`:

```json
{
  "default_provider": "local_lmstudio",
  "providers": {
    "local_lmstudio": {
      "endpoint": "http://localhost:1234/v1",
      "model": "local-model",
      "temperature": 0.7,
      "max_tokens": 4000
    },
    "openai": {
      "api_key": "YOUR_API_KEY",
      "model": "gpt-3.5-turbo",
      "temperature": 0.7,
      "max_tokens": 4000
    }
  }
}
```

### Search Configuration
Customize search behavior including:
- Default categories
- Result count per page
- Sort order preferences
- Category display options

### User Preferences
Arxer stores user preferences in:
- `.arxer_history.json`: Reading history
- `.arxer_favorites.json`: Saved papers and collections

## Requirements
- PHP 7.4 or higher
- Web server (Apache/Nginx)
- API keys for chosen AI providers
- Internet connection for ArXiv access

## Setup
1. Place files in web server directory
2. Configure AI providers in `ai_config.json`
3. Adjust default settings as needed
4. Access through web browser

## Usage Tips
- Use specific search terms for better results
- Save interesting papers to collections
- Use the knowledge graph to discover related research
- Leverage the AI explainer for complex papers
- Try different AI providers for varied explanations

## Privacy Note
When using OpenAI or other cloud providers, paper content is sent to external APIs. For maximum privacy, configure the local LM Studio option.
