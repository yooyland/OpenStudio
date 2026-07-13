/**
 * YooYActiveProject — session-scoped active Project Context.
 * Stores only { id, name }. No full project payload.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'yoy_active_project';
  var listeners = [];

  function read() {
    try {
      var raw = global.sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.id) return null;
      return {
        id: String(parsed.id),
        name: String(parsed.name || 'Project')
      };
    } catch (e) {
      return null;
    }
  }

  function write(value) {
    try {
      if (!value || !value.id) {
        global.sessionStorage.removeItem(STORAGE_KEY);
      } else {
        global.sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
          id: String(value.id),
          name: String(value.name || 'Project')
        }));
      }
    } catch (e) { /* ignore quota / private mode */ }
  }

  function emit(next) {
    listeners.slice().forEach(function (fn) {
      try { fn(next); } catch (e) { /* ignore listener errors */ }
    });
    try {
      global.document.dispatchEvent(new CustomEvent('yoy:active-project', { detail: next }));
    } catch (e) { /* ignore */ }
  }

  var Active = {
    set: function (project) {
      if (!project || !project.id) {
        this.clear();
        return null;
      }
      var next = {
        id: String(project.id),
        name: String(project.name || project.title || 'Project')
      };
      write(next);
      emit(next);
      return next;
    },

    get: function () {
      return read();
    },

    getId: function () {
      var cur = read();
      return cur ? cur.id : '';
    },

    clear: function () {
      write(null);
      emit(null);
    },

    subscribe: function (fn) {
      if (typeof fn !== 'function') return function () {};
      listeners.push(fn);
      return function unsubscribe() {
        listeners = listeners.filter(function (f) { return f !== fn; });
      };
    }
  };

  global.YooYActiveProject = Active;
})(typeof window !== 'undefined' ? window : this);
