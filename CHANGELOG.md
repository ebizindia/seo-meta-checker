# Changelog

All notable changes to the SEO Meta Checker Tool will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-02

### Added
- Initial public release
- Parallel crawling with cURL multi-handle for high performance
- Comprehensive SEO checks:
  - Title tag validation (missing/empty)
  - Meta description validation (missing/empty)
  - Canonical URL validation (missing/empty/format)
  - H1 heading validation (missing/multiple/empty)
  - Meta viewport detection
  - Meta charset detection
  - HTML language attribute detection
- Real-time progress updates during crawling
- Email reporting with color-coded issues
- Configurable page limits (1-1000 pages)
- Summary dashboard with statistics
- Performance metrics tracking
- Responsive Bootstrap 4 interface
- jQuery-powered interactive UI
- POST/Redirect/GET pattern to prevent form resubmission
- Memory optimization with immediate DOM cleanup
- URL normalization and caching
- Hash map for O(1) URL lookups
- Batch processing with configurable concurrent requests
- Execution time tracking
- Domain validation and SSRF protection

### Security
- Input sanitization using filter_var()
- Protection against infinite loops with max page limits
- Timeout protection for hung processes
- Domain validation to prevent cross-site crawling

### Performance
- Parallel request processing (10-20x faster than sequential)
- Hash map URL tracking for O(1) lookups
- URL caching to prevent redundant processing
- Memory-efficient batch processing
- Immediate DOM memory cleanup
- Configurable timeout and batch size

## [Unreleased]

### Planned Features
- CSV/Excel export functionality
- Google Search Console integration
- Scheduled crawling with cron jobs
- Multi-language support
- Advanced filtering and sorting options
- Open Graph and Twitter Card validation
- Schema.org markup detection
- Page speed insights integration
- Broken link detection
- Robots.txt validation
- Historical comparison reports
- Custom rule configuration
- API endpoint for programmatic access

---

## Version History

- **1.0.0** - Initial release with core SEO checking functionality
