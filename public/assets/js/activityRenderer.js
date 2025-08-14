/**
 * Activity Renderer - Handles rendering Activity Streams 2.0 objects to HTML
 * Provides type-specific rendering for different object types
 */

class ActivityRenderer {
  constructor() {
    this.templates = new Map();
    this.renderers = new Map();

    // Initialize templates and renderers
    this.initializeTemplates();
    this.initializeRenderers();
  }

  /**
     * Initialize HTML templates
     */
  initializeTemplates() {
    // Get templates from DOM
    this.templates.set('activity-item', document.getElementById('activity-item-template'));
    this.templates.set('media', document.getElementById('media-template'));
    this.templates.set('tag', document.getElementById('tag-template'));
  }

  /**
     * Initialize type-specific renderers
     */
  initializeRenderers() {
    // Register renderers for different Activity Streams object types
    this.renderers.set('Article', this.renderArticle.bind(this));
    this.renderers.set('Note', this.renderNote.bind(this));
    this.renderers.set('Image', this.renderImage.bind(this));
    this.renderers.set('Video', this.renderVideo.bind(this));
    this.renderers.set('Audio', this.renderAudio.bind(this));
    this.renderers.set('Document', this.renderDocument.bind(this));
    this.renderers.set('Link', this.renderLink.bind(this));

    // Activity type renderers
    this.renderers.set('Create', this.renderCreateActivity.bind(this));
    this.renderers.set('Update', this.renderUpdateActivity.bind(this));
    this.renderers.set('Announce', this.renderAnnounceActivity.bind(this));
    this.renderers.set('Like', this.renderLikeActivity.bind(this));
  }

  /**
     * Main render method - renders an activity item to HTML element
     */
  render(activityItem, container = null) {
    try {
      // Clone the activity item template
      const template = this.templates.get('activity-item');
      if (!template) {
        throw new Error('Activity item template not found');
      }

      const element = template.content.cloneNode(true);
      const article = element.querySelector('.activity-item');

      // Set basic attributes
      article.setAttribute('data-activity-type', activityItem.type);
      article.setAttribute('data-published', activityItem.published);
      article.setAttribute('data-feed-id', activityItem.feedId);

      // Render actor information
      this.renderActor(activityItem.actor, article);

      // Render timestamp
      this.renderTimestamp(activityItem.published, activityItem.updated, article);

      // Render main content based on type
      const contentType = activityItem.objectType || activityItem.type;
      const renderer = this.renderers.get(contentType) || this.renderDefault.bind(this);
      renderer(activityItem, article);

      // Render attachments
      this.renderAttachments(activityItem.attachment || [], article);

      // Render tags
      this.renderTags(activityItem.tag || [], article);

      // Render footer metadata
      this.renderFooter(activityItem, article);

      // Add to container if provided
      if (container) {
        container.appendChild(element);
        return article;
      }

      return element;
    } catch (error) {
      AnsyblConfig.utils.log('error', 'Failed to render activity item', error);
      return this.renderError(activityItem, error);
    }
  }

  /**
     * Render actor information
     */
  renderActor(actor, element) {
    if (!actor) return;

    const actorElement = element.querySelector('.activity-actor');
    const avatar = actorElement.querySelector('.actor-avatar');
    const name = actorElement.querySelector('.actor-name');
    const summary = actorElement.querySelector('.actor-summary');

    // Set avatar
    if (actor.icon) {
      avatar.src = actor.icon;
      avatar.alt = `${actor.name} avatar`;
    } else {
      avatar.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48"><rect width="48" height="48" fill="%23ddd"/><text x="24" y="28" text-anchor="middle" font-size="14" fill="%23999">?</text></svg>';
      avatar.alt = 'Default avatar';
    }

    // Set name
    name.textContent = actor.name || 'Unknown Author';
    if (actor.url) {
      const link = document.createElement('a');
      link.href = actor.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = name.textContent;
      name.textContent = '';
      name.appendChild(link);
    }

    // Set summary/bio
    if (actor.summary) {
      summary.textContent = AnsyblConfig.utils.truncateText(actor.summary, 100);
    } else {
      summary.style.display = 'none';
    }
  }

  /**
     * Render timestamp
     */
  renderTimestamp(published, updated, element) {
    const timeElement = element.querySelector('.activity-time');

    const publishedDate = new Date(published);
    timeElement.setAttribute('datetime', publishedDate.toISOString());
    timeElement.textContent = AnsyblConfig.utils.formatDate(published);

    if (updated && updated !== published) {
      timeElement.title = `Published: ${AnsyblConfig.utils.formatDate(published)}\nUpdated: ${AnsyblConfig.utils.formatDate(updated)}`;
    }
  }

  /**
     * Render attachments
     */
  renderAttachments(attachments, element) {
    const container = element.querySelector('.activity-attachments');

    if (!attachments || attachments.length === 0) {
      container.style.display = 'none';
      return;
    }

    attachments.forEach((attachment) => {
      const mediaElement = this.renderAttachment(attachment);
      if (mediaElement) {
        container.appendChild(mediaElement);
      }
    });
  }

  /**
     * Render single attachment
     */
  renderAttachment(attachment) {
    const template = this.templates.get('media');
    if (!template) return null;

    const element = template.content.cloneNode(true);
    const container = element.querySelector('.media-attachment');
    const image = container.querySelector('.media-image');
    const audio = container.querySelector('.media-audio');
    const video = container.querySelector('.media-video');
    const caption = container.querySelector('.media-caption');

    container.setAttribute('data-media-type', attachment.type);

    // Determine media type and show appropriate element
    const mediaType = attachment.mediaType || '';

    if (mediaType.startsWith('image/')) {
      image.src = attachment.url;
      image.alt = attachment.name || 'Media attachment';
      image.style.display = 'block';

      // Add click handler for full-size view
      image.addEventListener('click', () => {
        this.showFullSizeImage(attachment.url, attachment.name);
      });
    } else if (mediaType.startsWith('audio/')) {
      const source = audio.querySelector('source');
      source.src = attachment.url;
      source.type = mediaType;
      audio.style.display = 'block';
    } else if (mediaType.startsWith('video/')) {
      const source = video.querySelector('source');
      source.src = attachment.url;
      source.type = mediaType;
      video.style.display = 'block';
    } else {
      // Default to image for unknown types with common image extensions
      const url = attachment.url || '';
      if (url.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
        image.src = url;
        image.alt = attachment.name || 'Media attachment';
        image.style.display = 'block';
      } else {
        // Show as link for other file types
        const link = document.createElement('a');
        link.href = attachment.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = attachment.name || 'Download';
        link.className = 'media-link';
        container.appendChild(link);
      }
    }

    // Set caption
    if (attachment.name || attachment.summary) {
      caption.textContent = attachment.name || attachment.summary;
    } else {
      caption.style.display = 'none';
    }

    return element;
  }

  /**
     * Render tags
     */
  renderTags(tags, element) {
    const container = element.querySelector('.activity-tags');

    if (!tags || tags.length === 0) {
      container.style.display = 'none';
      return;
    }

    tags.forEach((tag) => {
      const tagElement = this.renderTag(tag);
      if (tagElement) {
        container.appendChild(tagElement);
      }
    });
  }

  /**
     * Render single tag
     */
  renderTag(tag) {
    const template = this.templates.get('tag');
    if (!template) return null;

    const element = template.content.cloneNode(true);
    const container = element.querySelector('.tag');
    const link = container.querySelector('.tag-link');

    const tagName = typeof tag === 'string' ? tag : (tag.name || tag.href);
    const tagUrl = typeof tag === 'object' ? tag.href : null;

    container.setAttribute('data-tag-type', typeof tag === 'object' ? tag.type : 'Hashtag');

    link.textContent = tagName.startsWith('#') ? tagName : `#${tagName}`;

    if (tagUrl) {
      link.href = tagUrl;
      link.target = '_blank';
      link.rel = 'noopener';
    } else {
      link.href = '#';
      link.addEventListener('click', (e) => {
        e.preventDefault();
        // Emit tag click event for filtering
        this.emitTagClick(tagName);
      });
    }

    return element;
  }

  /**
     * Render footer metadata
     */
  renderFooter(activityItem, element) {
    const footer = element.querySelector('.activity-footer');
    const meta = footer.querySelector('.activity-meta');
    const actions = footer.querySelector('.activity-actions');

    // Set feed source
    const feedSource = meta.querySelector('.feed-source');
    const { feedManager } = window;
    if (feedManager) {
      const feedInfo = feedManager.getFeedInfo().find((f) => f.id === activityItem.feedId);
      feedSource.textContent = feedInfo ? feedInfo.name : activityItem.feedId;
    }

    // Set activity type
    const activityType = meta.querySelector('.activity-type');
    activityType.textContent = activityItem.type;

    // Set external link
    const externalLink = actions.querySelector('.external-link');
    if (activityItem.url || activityItem.objectUrl) {
      externalLink.href = activityItem.url || activityItem.objectUrl;
    } else {
      externalLink.style.display = 'none';
    }

    // Set up share button
    const shareButton = actions.querySelector('.share-button');
    shareButton.addEventListener('click', () => {
      this.shareActivity(activityItem);
    });
  }

  /**
     * Type-specific renderers
     */

  renderArticle(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Untitled Article';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = AnsyblConfig.utils.truncateText(
        activityItem.objectSummary || activityItem.summary,
      );
    } else {
      summary.style.display = 'none';
    }

    // Render article content
    const articleContent = activityItem.objectContent || activityItem.content;
    if (articleContent) {
      // Simple HTML content rendering (could be enhanced with markdown support)
      content.innerHTML = this.sanitizeHTML(articleContent);
    }
  }

  renderNote(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    // Notes typically don't have titles, so use summary or truncated content
    const noteContent = activityItem.objectContent || activityItem.content
                            || activityItem.objectSummary || activityItem.summary;

    if (noteContent && noteContent.length > 100) {
      title.textContent = AnsyblConfig.utils.truncateText(noteContent, 50);
      summary.textContent = AnsyblConfig.utils.truncateText(noteContent);
    } else {
      title.textContent = noteContent || 'Note';
      summary.style.display = 'none';
    }

    if (noteContent) {
      content.innerHTML = this.sanitizeHTML(noteContent);
    }
  }

  renderImage(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Image';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = activityItem.objectSummary || activityItem.summary;
    } else {
      summary.style.display = 'none';
    }

    // Create image element
    if (activityItem.objectUrl || activityItem.url) {
      const img = document.createElement('img');
      img.src = activityItem.objectUrl || activityItem.url;
      img.alt = title.textContent;
      img.className = 'object-image';
      img.style.maxWidth = '100%';
      img.style.height = 'auto';
      img.style.borderRadius = 'var(--border-radius)';

      img.addEventListener('click', () => {
        this.showFullSizeImage(img.src, title.textContent);
      });

      content.appendChild(img);
    }
  }

  renderVideo(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Video';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = activityItem.objectSummary || activityItem.summary;
    } else {
      summary.style.display = 'none';
    }

    // Create video element
    if (activityItem.objectUrl || activityItem.url) {
      const video = document.createElement('video');
      video.src = activityItem.objectUrl || activityItem.url;
      video.controls = true;
      video.className = 'object-video';
      video.style.maxWidth = '100%';
      video.style.height = 'auto';
      video.style.borderRadius = 'var(--border-radius)';

      content.appendChild(video);
    }
  }

  renderAudio(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Audio';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = activityItem.objectSummary || activityItem.summary;
    } else {
      summary.style.display = 'none';
    }

    // Create audio element
    if (activityItem.objectUrl || activityItem.url) {
      const audio = document.createElement('audio');
      audio.src = activityItem.objectUrl || activityItem.url;
      audio.controls = true;
      audio.className = 'object-audio';
      audio.style.width = '100%';

      content.appendChild(audio);
    }
  }

  renderDocument(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Document';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = activityItem.objectSummary || activityItem.summary;
    } else {
      summary.style.display = 'none';
    }

    // Create download link
    if (activityItem.objectUrl || activityItem.url) {
      const link = document.createElement('a');
      link.href = activityItem.objectUrl || activityItem.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = 'Download Document';
      link.className = 'document-link';
      link.style.display = 'inline-block';
      link.style.padding = 'var(--spacing-sm) var(--spacing-md)';
      link.style.background = 'var(--primary-color)';
      link.style.color = 'white';
      link.style.textDecoration = 'none';
      link.style.borderRadius = 'var(--border-radius)';

      content.appendChild(link);
    }
  }

  renderLink(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.objectName || activityItem.name || 'Link';

    if (activityItem.objectSummary || activityItem.summary) {
      summary.textContent = activityItem.objectSummary || activityItem.summary;
    } else {
      summary.style.display = 'none';
    }

    // Create link preview
    if (activityItem.objectUrl || activityItem.url) {
      const linkContainer = document.createElement('div');
      linkContainer.className = 'link-preview';
      linkContainer.style.border = '1px solid #e9ecef';
      linkContainer.style.borderRadius = 'var(--border-radius)';
      linkContainer.style.padding = 'var(--spacing-md)';
      linkContainer.style.background = '#f8f9fa';

      const link = document.createElement('a');
      link.href = activityItem.objectUrl || activityItem.url;
      link.target = '_blank';
      link.rel = 'noopener';
      link.textContent = activityItem.objectUrl || activityItem.url;
      link.style.wordBreak = 'break-all';

      linkContainer.appendChild(link);
      content.appendChild(linkContainer);
    }
  }

  /**
     * Activity-specific renderers
     */

  renderCreateActivity(activityItem, element) {
    // For Create activities, render the created object
    if (activityItem.object) {
      const objectType = activityItem.object.type || 'Object';
      const renderer = this.renderers.get(objectType) || this.renderDefault.bind(this);
      renderer(activityItem, element);
    } else {
      this.renderDefault(activityItem, element);
    }
  }

  renderUpdateActivity(activityItem, element) {
    // Similar to Create, but with update indication
    const title = element.querySelector('.activity-title');
    const original = title.textContent;
    title.textContent = `Updated: ${original}`;

    this.renderCreateActivity(activityItem, element);
  }

  renderAnnounceActivity(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');

    title.textContent = 'Shared a post';
    summary.textContent = activityItem.summary || 'Shared content from another source';

    // Render the announced object if available
    if (activityItem.object) {
      this.renderCreateActivity(activityItem, element);
    }
  }

  renderLikeActivity(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');

    title.textContent = 'Liked a post';
    summary.textContent = activityItem.summary || 'Liked content';

    // Show what was liked if available
    if (activityItem.object && activityItem.object.name) {
      summary.textContent = `Liked: ${activityItem.object.name}`;
    }
  }

  /**
     * Default renderer for unknown types
     */
  renderDefault(activityItem, element) {
    const title = element.querySelector('.activity-title');
    const summary = element.querySelector('.activity-summary');
    const content = element.querySelector('.activity-object');

    title.textContent = activityItem.name || activityItem.objectName
                           || `${activityItem.type} Activity`;

    if (activityItem.summary || activityItem.objectSummary) {
      summary.textContent = AnsyblConfig.utils.truncateText(
        activityItem.summary || activityItem.objectSummary,
      );
    } else {
      summary.style.display = 'none';
    }

    // Show basic content if available
    const itemContent = activityItem.content || activityItem.objectContent;
    if (itemContent) {
      content.innerHTML = this.sanitizeHTML(itemContent);
    }
  }

  /**
     * Error renderer
     */
  renderError(activityItem, error) {
    const div = document.createElement('div');
    div.className = 'activity-item error';
    div.innerHTML = `
            <div class="activity-header">
                <h3>Error rendering activity</h3>
            </div>
            <div class="activity-content">
                <p>Unable to render activity item.</p>
                <details>
                    <summary>Error details</summary>
                    <pre>${error.message}</pre>
                </details>
            </div>
        `;
    return div;
  }

  /**
     * Utility methods
     */

  /**
     * Basic HTML sanitization
     */
  sanitizeHTML(html) {
    // Basic sanitization - in production, use a proper HTML sanitizer like DOMPurify
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  }

  /**
     * Show full-size image in modal
     */
  showFullSizeImage(src, alt) {
    // Simple modal implementation
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            cursor: pointer;
        `;

    const img = document.createElement('img');
    img.src = src;
    img.alt = alt;
    img.style.cssText = `
            max-width: 90%;
            max-height: 90%;
            border-radius: var(--border-radius);
        `;

    modal.appendChild(img);
    modal.addEventListener('click', () => {
      document.body.removeChild(modal);
    });

    document.body.appendChild(modal);
  }

  /**
     * Emit tag click event
     */
  emitTagClick(tagName) {
    const event = new CustomEvent('tagClick', {
      detail: { tag: tagName },
    });
    document.dispatchEvent(event);
  }

  /**
     * Share activity
     */
  async shareActivity(activityItem) {
    const shareData = {
      title: activityItem.name || activityItem.objectName || 'Shared from Ansybl Site',
      text: activityItem.summary || activityItem.objectSummary || '',
      url: activityItem.url || activityItem.objectUrl || window.location.href,
    };

    if (navigator.share && AnsyblConfig.utils.isFeatureEnabled('sharing')) {
      try {
        await navigator.share(shareData);
      } catch (error) {
        // Fallback to clipboard
        this.copyToClipboard(shareData.url);
      }
    } else {
      this.copyToClipboard(shareData.url);
    }
  }

  /**
     * Copy to clipboard fallback
     */
  async copyToClipboard(text) {
    try {
      await navigator.clipboard.writeText(text);
      // Show toast notification
      this.showToast('Link copied to clipboard');
    } catch (error) {
      console.warn('Could not copy to clipboard', error);
    }
  }

  /**
     * Show toast notification
     */
  showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        `;

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'slideOut 0.3s ease-out';
      setTimeout(() => {
        document.body.removeChild(toast);
      }, 300);
    }, 3000);
  }
}

// Make ActivityRenderer globally available
window.ActivityRenderer = ActivityRenderer;
