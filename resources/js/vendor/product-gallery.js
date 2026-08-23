export function registerVendorProductGallery(Alpine) {
    Alpine.data('vendorProductGallery', (config) => ({
        canEdit: Boolean(config.canEdit),
        maxImages: config.maxImages || 8,
        maxBytes: config.maxBytes || 5 * 1024 * 1024,
        acceptedTypes: config.acceptedTypes || ['image/jpeg', 'image/png', 'image/webp'],
        primaryImageId: config.primaryImageId ?? null,
        serverImages: (config.images || []).map((image) => normalizeImage(image)),
        images: (config.images || []).map((image) => normalizeImage(image)),
        routes: config.routes || {},
        labels: config.labels || {},
        queue: [],
        uploading: false,
        orderDirty: false,
        staleOrder: false,
        busyAction: null,
        statusMessage: '',
        statusTone: 'neutral',
        expandedAltId: null,
        altDrafts: {},
        beforeUnloadBound: false,

        init() {
            this.syncAltDraftsFromServer();
            this.bindBeforeUnload();
            this.$watch('orderDirty', () => this.emitGalleryState());
            this.$watch('uploading', () => this.emitGalleryState());
            this.$watch('busyAction', () => this.emitGalleryState());
            this.$watch('queue', () => this.emitGalleryState(), { deep: true });
            this.$watch('altDrafts', () => this.emitGalleryState(), { deep: true });
            this.$watch('images', () => this.emitGalleryState(), { deep: true });
            this.emitGalleryState();
        },

        emitGalleryState() {
            window.dispatchEvent(new CustomEvent('vendor-product-gallery-state', {
                detail: {
                    orderDirty: this.orderDirty,
                    altDirty: this.hasDirtyAlt,
                    busy: this.uploading || this.activeQueueCount > 0 || Boolean(this.busyAction),
                },
            }));
        },

        emitReadinessUpdate(payload) {
            if (!payload?.readiness) {
                return;
            }

            window.dispatchEvent(new CustomEvent('vendor-product-readiness-update', {
                detail: { readiness: payload.readiness },
            }));
        },

        get remainingSlots() {
            return Math.max(0, this.maxImages - this.images.length - this.activeQueueCount);
        },

        get activeQueueCount() {
            return this.queue.filter((item) => item.state === 'queued' || item.state === 'uploading').length;
        },

        get isFull() {
            return this.images.length >= this.maxImages;
        },

        get slotsLabel() {
            if (this.isFull) {
                return this.labels.slotsFull || '';
            }

            return (this.labels.remainingSlots || '')
                .replace(':count', String(this.remainingSlots))
                .replace(':max', String(this.maxImages));
        },

        get dirtyAltIds() {
            return this.images
                .filter((image) => this.isAltDraftDirty(image.id))
                .map((image) => image.id);
        },

        get hasDirtyAlt() {
            return this.dirtyAltIds.length > 0;
        },

        get hasUnsavedWork() {
            return this.orderDirty
                || this.hasDirtyAlt
                || this.uploading
                || this.activeQueueCount > 0;
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },

        bindBeforeUnload() {
            if (this.beforeUnloadBound) {
                return;
            }

            this._onBeforeUnload = (event) => {
                if (!this.hasUnsavedWork) {
                    return;
                }

                event.preventDefault();
                event.returnValue = this.labels.unsavedWarning || '';
            };

            window.addEventListener('beforeunload', this._onBeforeUnload);
            this.beforeUnloadBound = true;
        },

        cloneImages(images) {
            return images.map((image) => normalizeImage(image));
        },

        syncAltDraftsFromServer(preserveDirty = false) {
            const drafts = { ...this.altDrafts };

            this.serverImages.forEach((image) => {
                if (preserveDirty && this.isAltDraftDirty(image.id)) {
                    return;
                }

                drafts[image.id] = {
                    ar: image.altAr || '',
                    en: image.altEn || '',
                };
            });

            Object.keys(drafts).forEach((id) => {
                if (!this.serverImages.some((image) => String(image.id) === String(id))) {
                    if (!preserveDirty || !this.isAltDraftDirty(id)) {
                        delete drafts[id];
                    }
                }
            });

            this.altDrafts = drafts;
        },

        isAltDraftDirty(imageId) {
            const draft = this.altDrafts[imageId];
            const server = this.serverImages.find((image) => Number(image.id) === Number(imageId));

            if (!draft || !server) {
                return Boolean(draft);
            }

            return String(draft.ar ?? '') !== String(server.altAr ?? '')
                || String(draft.en ?? '') !== String(server.altEn ?? '');
        },

        setStatus(message, tone = 'neutral') {
            this.statusMessage = message;
            this.statusTone = tone;
        },

        queueStateLabel(item) {
            if (item.state === 'uploading') {
                return this.labels.uploading;
            }

            if (item.state === 'completed') {
                return this.labels.completed;
            }

            if (item.state === 'failed') {
                return item.error || this.labels.uploadFailed;
            }

            return this.labels.queued;
        },

        revokeQueueItemUrl(item) {
            if (item?.previewUrl) {
                URL.revokeObjectURL(item.previewUrl);
                item.previewUrl = '';
            }
        },

        dismissQueueItem(id) {
            const item = this.queue.find((entry) => entry.id === id);
            if (!item || item.state === 'uploading' || item.state === 'queued') {
                return;
            }

            this.revokeQueueItemUrl(item);
            this.queue = this.queue.filter((entry) => entry.id !== id);
        },

        revokeAllQueueUrls() {
            this.queue.forEach((item) => this.revokeQueueItemUrl(item));
        },

        applyGallery(gallery, options = {}) {
            if (!gallery) {
                return;
            }

            const preserveOrder = Boolean(options.preserveOrder && this.orderDirty);
            const preserveAlt = Boolean(options.preserveAlt ?? true);
            const nextServer = (gallery.images || []).map((image) => normalizeImage(image));

            this.primaryImageId = gallery.primary_image_id ?? null;
            this.serverImages = nextServer;

            if (preserveOrder) {
                const byId = Object.fromEntries(nextServer.map((image) => [String(image.id), image]));
                const kept = this.images
                    .filter((image) => byId[String(image.id)])
                    .map((image) => ({ ...byId[String(image.id)], position: image.position }));
                const appended = nextServer.filter(
                    (image) => !this.images.some((local) => Number(local.id) === Number(image.id)),
                );
                this.images = [...kept, ...appended].map((image, position) => ({ ...image, position }));
                this.orderDirty = this.localOrderIds().join(',') !== this.serverOrderIds().join(',');
            } else {
                this.images = this.cloneImages(nextServer);
                this.orderDirty = false;
            }

            this.staleOrder = false;
            this.syncAltDraftsFromServer(preserveAlt);
        },

        localOrderIds() {
            return this.images.map((image) => Number(image.id));
        },

        serverOrderIds() {
            return this.serverImages.map((image) => Number(image.id));
        },

        discardOrderChanges() {
            this.images = this.cloneImages(this.serverImages);
            this.orderDirty = false;
            this.staleOrder = false;
            this.setStatus('', 'neutral');
        },

        discardAlt(image) {
            const server = this.serverImages.find((row) => Number(row.id) === Number(image.id));
            this.altDrafts = {
                ...this.altDrafts,
                [image.id]: {
                    ar: server?.altAr || '',
                    en: server?.altEn || '',
                },
            };
        },

        guardConflicts(kind) {
            if (this.orderDirty && kind !== 'order' && kind !== 'alt') {
                this.setStatus(this.labels.saveOrderFirst, 'danger');
                return false;
            }

            if (this.hasDirtyAlt && kind !== 'alt') {
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return false;
            }

            return true;
        },

        async request(url, options = {}) {
            let response;

            try {
                response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(options.body instanceof FormData
                            ? { 'X-CSRF-TOKEN': this.csrfToken() }
                            : {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            }),
                        ...(options.headers || {}),
                    },
                    ...options,
                });
            } catch {
                throw new RequestError(this.labels.networkError || 'Network error', 'network');
            }

            if (response.status === 401 || response.status === 403) {
                throw new RequestError(this.labels.forbidden || 'Forbidden', 'forbidden');
            }

            if (response.status === 419) {
                throw new RequestError(this.labels.sessionExpired || 'Session expired', 'session');
            }

            if (response.status === 404) {
                throw new RequestError(this.labels.notFound || 'Not found', 'notfound');
            }

            if (response.status === 422) {
                const payload = await response.json().catch(() => ({}));
                const message = extractValidationMessage(payload) || this.labels.validationError;
                throw new RequestError(message, 'validation');
            }

            if (response.status >= 500) {
                throw new RequestError(this.labels.serverError || 'Server error', 'server');
            }

            if (!response.ok) {
                throw new RequestError(this.labels.serverError || 'Request failed', 'server');
            }

            return response.json();
        },

        validateClientFile(file) {
            if (!this.acceptedTypes.includes(file.type)) {
                return this.labels.invalidType || this.labels.validationError;
            }

            if (file.size > this.maxBytes) {
                return this.labels.fileTooLarge || this.labels.validationError;
            }

            return null;
        },

        openFilePicker() {
            if (!this.canEdit || this.isFull || this.uploading) {
                return;
            }

            if (!this.guardConflicts('upload')) {
                return;
            }

            this.$refs.fileInput?.click();
        },

        onFileInputChange(event) {
            this.enqueueFiles(event.target.files);
            event.target.value = '';
        },

        onDrop(event) {
            event.preventDefault();
            if (!this.canEdit || this.isFull) {
                return;
            }

            if (!this.guardConflicts('upload')) {
                return;
            }

            this.enqueueFiles(event.dataTransfer?.files);
        },

        onDragOver(event) {
            event.preventDefault();
        },

        enqueueFiles(fileList) {
            if (!fileList || !this.canEdit) {
                return;
            }

            if (!this.guardConflicts('upload')) {
                return;
            }

            Array.from(fileList).forEach((file) => {
                const error = this.validateClientFile(file);
                if (!error && this.remainingSlots <= 0) {
                    return;
                }

                const previewUrl = URL.createObjectURL(file);

                this.queue.push({
                    id: `${Date.now()}-${Math.random()}`,
                    file,
                    name: file.name,
                    previewUrl,
                    state: error ? 'failed' : 'queued',
                    error: error || '',
                });
            });

            this.processQueue();
        },

        async processQueue() {
            if (this.uploading) {
                return;
            }

            const next = this.queue.find((item) => item.state === 'queued');
            if (!next || this.isFull) {
                return;
            }

            if (!this.guardConflicts('upload')) {
                next.state = 'failed';
                next.error = this.statusMessage;
                return;
            }

            this.uploading = true;
            next.state = 'uploading';
            this.setStatus(this.labels.uploading || 'Uploading…', 'info');

            try {
                const body = new FormData();
                body.append('image', next.file);
                const payload = await this.request(this.routes.upload, { method: 'POST', body });
                this.applyGallery(payload.gallery, { preserveOrder: true, preserveAlt: true });
                this.emitReadinessUpdate(payload);
                next.state = 'completed';
                this.setStatus(payload.status || this.labels.uploadComplete || 'Upload complete', 'success');
                Alpine.store('toasts')?.push(payload.status || this.labels.uploadComplete);
            } catch (error) {
                next.state = 'failed';
                next.error = error instanceof RequestError ? error.message : this.labels.uploadFailed;
                this.setStatus(next.error, 'danger');
            } finally {
                this.uploading = false;
                this.processQueue();
            }
        },

        async saveOrder() {
            if (!this.canEdit || !this.orderDirty || this.busyAction) {
                return;
            }

            if (this.hasDirtyAlt) {
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return;
            }

            this.busyAction = 'reorder';
            try {
                const payload = await this.request(this.routes.reorder, {
                    method: 'PUT',
                    body: JSON.stringify({
                        image_ids: this.images.map((image) => image.id),
                    }),
                });
                this.applyGallery(payload.gallery, { preserveOrder: false, preserveAlt: true });
                this.setStatus(payload.status || this.labels.orderSaved || 'Order saved', 'success');
                Alpine.store('toasts')?.push(payload.status || this.labels.orderSaved);
            } catch (error) {
                const message = error instanceof RequestError ? error.message : this.labels.networkError;
                if (error instanceof RequestError && error.kind === 'validation') {
                    this.staleOrder = true;
                    this.setStatus(this.labels.orderStale || message, 'danger');
                } else {
                    this.setStatus(message, 'danger');
                }
            } finally {
                this.busyAction = null;
            }
        },

        confirmStaleRefresh() {
            if (!window.confirm(this.labels.confirmStaleRefresh || this.labels.orderStale)) {
                return;
            }

            window.location.reload();
        },

        async setPrimary(image) {
            if (!this.canEdit || !image?.routes?.primary || this.busyAction) {
                return;
            }

            if (!this.guardConflicts('primary')) {
                return;
            }

            this.busyAction = `primary-${image.id}`;
            try {
                const payload = await this.request(image.routes.primary, { method: 'PUT' });
                this.applyGallery(payload.gallery, { preserveOrder: true, preserveAlt: true });
                this.setStatus(payload.status || this.labels.primaryUpdated || 'Primary updated', 'success');
            } catch (error) {
                this.setStatus(error instanceof RequestError ? error.message : this.labels.networkError, 'danger');
            } finally {
                this.busyAction = null;
            }
        },

        toggleAltEditor(imageId) {
            this.expandedAltId = this.expandedAltId === imageId ? null : imageId;
        },

        async saveAlt(image) {
            if (!this.canEdit || !image?.routes?.translations || this.busyAction) {
                return;
            }

            if (this.orderDirty) {
                this.setStatus(this.labels.saveOrderFirst, 'danger');
                return;
            }

            const draft = this.altDrafts[image.id] || { ar: '', en: '' };
            this.busyAction = `alt-${image.id}`;

            try {
                const payload = await this.request(image.routes.translations, {
                    method: 'PUT',
                    body: JSON.stringify({
                        translations: {
                            ar: { alt_text: draft.ar },
                            en: { alt_text: draft.en },
                        },
                    }),
                });
                this.applyGallery(payload.gallery, { preserveOrder: true, preserveAlt: false });
                this.setStatus(payload.status || this.labels.altSaved || 'Alt saved', 'success');
            } catch (error) {
                this.setStatus(error instanceof RequestError ? error.message : this.labels.altFailed, 'danger');
            } finally {
                this.busyAction = null;
            }
        },

        async removeImage(image) {
            if (!this.canEdit || !image?.routes?.destroy || this.busyAction) {
                return;
            }

            if (!this.guardConflicts('remove')) {
                return;
            }

            if (!window.confirm(this.labels.removeConfirm || 'Remove this image?')) {
                return;
            }

            this.busyAction = `remove-${image.id}`;
            try {
                const payload = await this.request(image.routes.destroy, { method: 'DELETE' });
                this.applyGallery(payload.gallery, { preserveOrder: false, preserveAlt: true });
                this.emitReadinessUpdate(payload);
                this.setStatus(payload.status || this.labels.imageRemoved || 'Removed', 'success');
                Alpine.store('toasts')?.push(payload.status || this.labels.imageRemoved);
            } catch (error) {
                this.setStatus(error instanceof RequestError ? error.message : this.labels.networkError, 'danger');
            } finally {
                this.busyAction = null;
            }
        },

        moveEarlier(index) {
            if (!this.canEdit || index <= 0) {
                return;
            }

            if (this.hasDirtyAlt) {
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return;
            }

            this.swapImages(index, index - 1);
        },

        moveLater(index) {
            if (!this.canEdit || index >= this.images.length - 1) {
                return;
            }

            if (this.hasDirtyAlt) {
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return;
            }

            this.swapImages(index, index + 1);
        },

        swapImages(from, to) {
            const next = [...this.images];
            [next[from], next[to]] = [next[to], next[from]];
            this.images = next.map((image, position) => ({ ...image, position }));
            this.orderDirty = this.localOrderIds().join(',') !== this.serverOrderIds().join(',');
        },

        onHandleDragStart(event, index) {
            if (!this.canEdit) {
                return;
            }

            if (this.hasDirtyAlt) {
                event.preventDefault();
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return;
            }

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(index));
        },

        onDropOnCard(event, index) {
            event.preventDefault();
            if (!this.canEdit) {
                return;
            }

            if (this.hasDirtyAlt) {
                this.setStatus(this.labels.saveAltFirst, 'danger');
                return;
            }

            const from = Number(event.dataTransfer.getData('text/plain'));
            if (Number.isNaN(from) || from === index) {
                return;
            }

            const next = [...this.images];
            const [moved] = next.splice(from, 1);
            next.splice(index, 0, moved);
            this.images = next.map((image, position) => ({ ...image, position }));
            this.orderDirty = this.localOrderIds().join(',') !== this.serverOrderIds().join(',');
        },

        markImageFailed(image) {
            image.loadFailed = true;
        },

        altStatusLabel(image) {
            if ((image.altAr && image.altAr.trim() !== '') || (image.altEn && image.altEn.trim() !== '')) {
                return this.labels.altStatusSet;
            }

            return this.labels.altStatusFallback;
        },

        isBusy(key) {
            return this.busyAction === key;
        },

        destroy() {
            this.revokeAllQueueUrls();
            if (this.beforeUnloadBound && this._onBeforeUnload) {
                window.removeEventListener('beforeunload', this._onBeforeUnload);
            }
        },
    }));
}

class RequestError extends Error {
    constructor(message, kind) {
        super(message);
        this.kind = kind;
    }
}

function normalizeImage(image) {
    return {
        ...image,
        isPrimary: Boolean(image.isPrimary),
        loadFailed: Boolean(image.loadFailed),
    };
}

function extractValidationMessage(payload) {
    const errors = payload?.errors;
    if (!errors || typeof errors !== 'object') {
        return payload?.message || null;
    }

    const firstKey = Object.keys(errors)[0];

    return firstKey ? (errors[firstKey][0] || null) : null;
}
