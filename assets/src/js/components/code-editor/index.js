/**
 * Code Editor Component
 * Wrapper for WordPress code editor with enhancements
 */

import React, { useEffect, useRef, forwardRef, useImperativeHandle } from 'react';

const CodeEditor = forwardRef(({ value, onChange, language = 'php', height = '400px', readOnly = false }, ref) => {
    const textareaRef = useRef(null);
    const editorRef = useRef(null);

    useImperativeHandle(ref, () => ({
        getValue: () => editorRef.current?.codemirror?.getValue() || value,
        setValue: (newValue) => {
            if (editorRef.current?.codemirror) {
                editorRef.current.codemirror.setValue(newValue);
            }
        },
        refresh: () => {
            if (editorRef.current?.codemirror) {
                editorRef.current.codemirror.refresh();
            }
        }
    }));

    useEffect(() => {
        if (!textareaRef.current || !window.wp?.codeEditor) {
            return;
        }

        // Initialize WordPress code editor
        const settings = {
            codemirror: {
                mode: language === 'php' ? 'text/x-php' : language,
                lineNumbers: true,
                lineWrapping: true,
                indentUnit: 4,
                tabSize: 4,
                theme: 'default',
                readOnly: readOnly,
                extraKeys: {
                    'Ctrl-Space': 'autocomplete',
                    'Cmd-/': 'toggleComment',
                    'Ctrl-/': 'toggleComment'
                }
            }
        };

        try {
            editorRef.current = wp.codeEditor.initialize(textareaRef.current, settings);
            
            // Set initial value
            if (value && editorRef.current.codemirror) {
                editorRef.current.codemirror.setValue(value);
            }

            // Handle changes
            if (onChange && editorRef.current.codemirror) {
                editorRef.current.codemirror.on('change', (cm) => {
                    onChange(cm.getValue());
                });
            }

            // Set height
            if (editorRef.current.codemirror) {
                editorRef.current.codemirror.setSize(null, height);
            }
        } catch (error) {
            console.error('Failed to initialize code editor:', error);
        }

        // Cleanup
        return () => {
            if (editorRef.current?.codemirror) {
                editorRef.current.codemirror.toTextArea();
            }
        };
    }, [language, height, readOnly]);

    // Update value when prop changes
    useEffect(() => {
        if (editorRef.current?.codemirror && value !== editorRef.current.codemirror.getValue()) {
            const cursor = editorRef.current.codemirror.getCursor();
            editorRef.current.codemirror.setValue(value || '');
            editorRef.current.codemirror.setCursor(cursor);
        }
    }, [value]);

    return (
        <div className="wp-autoplugin-code-editor">
            <textarea
                ref={textareaRef}
                defaultValue={value}
                style={{ display: 'none' }}
            />
            <style>{`
                .wp-autoplugin-code-editor .CodeMirror {
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                    font-family: Consolas, Monaco, monospace;
                    font-size: 13px;
                }
                .wp-autoplugin-code-editor .CodeMirror-focused {
                    border-color: #2271b1;
                    box-shadow: 0 0 0 1px #2271b1;
                }
            `}</style>
        </div>
    );
});

CodeEditor.displayName = 'CodeEditor';

export default CodeEditor;