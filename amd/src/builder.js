/**
 * Course Calendar builder - client-side state management.
 *
 * Provides drag-and-drop, undo/redo, unsaved change tracking,
 * batch save, and beforeunload warning.
 *
 * @module local_coursecalendar/builder
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    'use strict';

    var state = {
        courseid: 0,
        calendarid: 0,
        dirty: false,
        undoStack: [],
        redoStack: [],
        dragSource: null,
    };

    var SELECTORS = {
        grid: '.local-coursecalendar-grid',
        cell: '[data-cc-row][data-cc-col]',
        saveAllBtn: '#local-coursecalendar-saveall',
        undoBtn: '#local-coursecalendar-undo',
        redoBtn: '#local-coursecalendar-redo',
        badge: '#local-coursecalendar-unsaved-badge',
        copyIframeBtn: '#local-coursecalendar-copy-iframe',
    };

    /**
     * Capture a snapshot of the current grid DOM for undo/redo.
     */
    function captureSnapshot() {
        var grid = document.querySelector(SELECTORS.grid);
        if (!grid) {
            return null;
        }
        return grid.innerHTML;
    }

    function pushUndo() {
        var snap = captureSnapshot();
        if (snap !== null) {
            state.undoStack.push(snap);
            if (state.undoStack.length > 50) {
                state.undoStack.shift();
            }
        }
        state.redoStack = [];
        markDirty();
    }

    function restoreSnapshot(html) {
        var grid = document.querySelector(SELECTORS.grid);
        if (!grid || !html) {
            return;
        }
        grid.innerHTML = html;
        bindDragEvents();
        markDirty();
    }

    function undo() {
        if (state.undoStack.length === 0) {
            return;
        }
        var currentSnap = captureSnapshot();
        state.redoStack.push(currentSnap);
        var prev = state.undoStack.pop();
        restoreSnapshot(prev);
    }

    function redo() {
        if (state.redoStack.length === 0) {
            return;
        }
        var currentSnap = captureSnapshot();
        state.undoStack.push(currentSnap);
        var next = state.redoStack.pop();
        restoreSnapshot(next);
    }

    function markDirty() {
        state.dirty = true;
        updateUI();
    }

    function markClean() {
        state.dirty = false;
        state.undoStack = [];
        state.redoStack = [];
        updateUI();
    }

    function updateUI() {
        var badge = document.querySelector(SELECTORS.badge);
        if (badge) {
            badge.style.display = state.dirty ? 'inline-block' : 'none';
        }
        var undoBtn = document.querySelector(SELECTORS.undoBtn);
        if (undoBtn) {
            undoBtn.disabled = state.undoStack.length === 0;
        }
        var redoBtn = document.querySelector(SELECTORS.redoBtn);
        if (redoBtn) {
            redoBtn.disabled = state.redoStack.length === 0;
        }
    }

    /**
     * Collect all editable cell data from data attributes on the grid.
     */
    function collectBlocks() {
        var grid = document.querySelector(SELECTORS.grid);
        if (!grid) {
            return [];
        }
        var cells = grid.querySelectorAll('[data-cc-row][data-cc-col]');
        var blocks = [];
        cells.forEach(function(cell) {
            var row = parseInt(cell.getAttribute('data-cc-row'), 10);
            var col = parseInt(cell.getAttribute('data-cc-col'), 10);
            var bt = cell.getAttribute('data-cc-blocktype') || '';
            if (!bt) {
                return;
            }
            blocks.push({
                rownum: row,
                colnum: col,
                blocktype: bt,
                contenthtml: cell.getAttribute('data-cc-content') || '',
                topicid: parseInt(cell.getAttribute('data-cc-topicid') || '0', 10),
                cellheading: cell.getAttribute('data-cc-cellheading') || '',
                headerday: cell.getAttribute('data-cc-headerday') || '',
                headermode: cell.getAttribute('data-cc-headermode') || '',
                highlighted: parseInt(cell.getAttribute('data-cc-highlighted') || '0', 10),
                verticallycentred: parseInt(cell.getAttribute('data-cc-vcentred') || '0', 10),
            });
        });
        return blocks;
    }

    function saveAll() {
        var blocks = collectBlocks();
        if (blocks.length === 0) {
            return;
        }
        var saveBtn = document.querySelector(SELECTORS.saveAllBtn);
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }
        Ajax.call([{
            methodname: 'local_coursecalendar_save_builder_grid',
            args: {
                courseid: state.courseid,
                calendarid: state.calendarid,
                blocks: blocks,
            },
            done: function(result) {
                if (result.status === 'ok') {
                    markClean();
                    Notification.addNotification({
                        message: result.saved + ' block(s) saved.',
                        type: 'success',
                    });
                }
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save All';
                }
            },
            fail: function(err) {
                Notification.exception(err);
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save All';
                }
            },
        }]);
    }

    // --- Drag and drop ---

    function getEditableCell(el) {
        while (el && el !== document) {
            if (el.hasAttribute && el.hasAttribute('data-cc-row') &&
                el.hasAttribute('data-cc-col') && el.getAttribute('data-cc-editable') === '1') {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    function handleDragStart(e) {
        var cell = getEditableCell(e.target);
        if (!cell) {
            return;
        }
        state.dragSource = cell;
        cell.classList.add('local-coursecalendar-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain',
            cell.getAttribute('data-cc-row') + ',' + cell.getAttribute('data-cc-col'));
    }

    function handleDragOver(e) {
        var cell = getEditableCell(e.target);
        if (!cell) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        cell.classList.add('local-coursecalendar-dragover');
    }

    function handleDragLeave(e) {
        var cell = getEditableCell(e.target);
        if (cell) {
            cell.classList.remove('local-coursecalendar-dragover');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        var targetCell = getEditableCell(e.target);
        if (!targetCell || !state.dragSource) {
            return;
        }
        targetCell.classList.remove('local-coursecalendar-dragover');
        state.dragSource.classList.remove('local-coursecalendar-dragging');

        var fromRow = parseInt(state.dragSource.getAttribute('data-cc-row'), 10);
        var fromCol = parseInt(state.dragSource.getAttribute('data-cc-col'), 10);
        var toRow = parseInt(targetCell.getAttribute('data-cc-row'), 10);
        var toCol = parseInt(targetCell.getAttribute('data-cc-col'), 10);

        if (fromRow === toRow && fromCol === toCol) {
            state.dragSource = null;
            return;
        }

        pushUndo();

        Ajax.call([{
            methodname: 'local_coursecalendar_swap_builder_cells',
            args: {
                courseid: state.courseid,
                calendarid: state.calendarid,
                fromrow: fromRow,
                fromcol: fromCol,
                torow: toRow,
                tocol: toCol,
            },
            done: function(result) {
                if (result.status === 'ok') {
                    window.location.reload();
                } else {
                    Notification.addNotification({message: result.message, type: 'error'});
                }
            },
            fail: Notification.exception,
        }]);

        state.dragSource = null;
    }

    function handleDragEnd() {
        if (state.dragSource) {
            state.dragSource.classList.remove('local-coursecalendar-dragging');
        }
        document.querySelectorAll('.local-coursecalendar-dragover').forEach(function(el) {
            el.classList.remove('local-coursecalendar-dragover');
        });
        state.dragSource = null;
    }

    function bindDragEvents() {
        var grid = document.querySelector(SELECTORS.grid);
        if (!grid) {
            return;
        }
        var editableCells = grid.querySelectorAll('[data-cc-editable="1"]');
        editableCells.forEach(function(cell) {
            cell.setAttribute('draggable', 'true');
            cell.removeEventListener('dragstart', handleDragStart);
            cell.addEventListener('dragstart', handleDragStart);
            cell.removeEventListener('dragover', handleDragOver);
            cell.addEventListener('dragover', handleDragOver);
            cell.removeEventListener('dragleave', handleDragLeave);
            cell.addEventListener('dragleave', handleDragLeave);
            cell.removeEventListener('drop', handleDrop);
            cell.addEventListener('drop', handleDrop);
            cell.removeEventListener('dragend', handleDragEnd);
            cell.addEventListener('dragend', handleDragEnd);
        });
    }

    function copyIframeCode() {
        var btn = document.querySelector(SELECTORS.copyIframeBtn);
        if (!btn) {
            return;
        }
        var previewUrl = btn.getAttribute('data-preview-url');
        if (!previewUrl) {
            return;
        }
        var iframe = '<iframe src="' + previewUrl + '" width="100%" height="600" ' +
            'style="border:1px solid #ccc;" loading="lazy"></iframe>';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(iframe).then(function() {
                Notification.addNotification({message: 'Iframe code copied to clipboard.', type: 'success'});
            }).catch(function() {
                window.prompt('Copy this iframe code:', iframe);
            });
        } else {
            window.prompt('Copy this iframe code:', iframe);
        }
    }

    /**
     * Track changes on form inputs inside the grid to set dirty flag.
     */
    function bindChangeTracking() {
        var grid = document.querySelector(SELECTORS.grid);
        if (!grid) {
            return;
        }
        grid.addEventListener('change', function() {
            if (!state.dirty) {
                pushUndo();
            }
        });
        grid.addEventListener('input', function(e) {
            if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                if (!state.dirty) {
                    pushUndo();
                }
            }
        });
    }

    return {
        init: function(courseid, calendarid) {
            state.courseid = courseid;
            state.calendarid = calendarid;

            bindDragEvents();
            bindChangeTracking();
            updateUI();

            var saveBtn = document.querySelector(SELECTORS.saveAllBtn);
            if (saveBtn) {
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    saveAll();
                });
            }

            var undoBtn = document.querySelector(SELECTORS.undoBtn);
            if (undoBtn) {
                undoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    undo();
                });
            }

            var redoBtn = document.querySelector(SELECTORS.redoBtn);
            if (redoBtn) {
                redoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    redo();
                });
            }

            var copyBtn = document.querySelector(SELECTORS.copyIframeBtn);
            if (copyBtn) {
                copyBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    copyIframeCode();
                });
            }

            // Keyboard shortcuts: Ctrl+S to save, Ctrl+Z undo, Ctrl+Shift+Z redo.
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && !e.altKey) {
                    if (e.key === 's' || e.key === 'S') {
                        e.preventDefault();
                        if (state.dirty) {
                            saveAll();
                        }
                    } else if (e.key === 'z' && !e.shiftKey) {
                        e.preventDefault();
                        undo();
                    } else if ((e.key === 'z' && e.shiftKey) || e.key === 'y') {
                        e.preventDefault();
                        redo();
                    }
                }
            });

            window.addEventListener('beforeunload', function(e) {
                if (state.dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },
    };
});
