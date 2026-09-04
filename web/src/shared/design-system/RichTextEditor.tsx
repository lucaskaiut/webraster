import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import ImageExtension from '@tiptap/extension-image'
import LinkExtension from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { Bold, Italic, List, ListOrdered, Quote, Undo2, Redo2, Heading2, Code2, Link2, FileCode } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Field } from './Field'
import { useCallback, useEffect, useRef, useState } from 'react'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
  label?: string
  hint?: string
  error?: string
  placeholder?: string
  className?: string
}

function ToolbarButton({ active, onClick, children, title }: { active?: boolean; onClick: () => void; children: React.ReactNode; title: string }) {
  return (
    <button
      type="button"
      title={title}
      onClick={onClick}
      className={cn(
        'flex size-8 cursor-pointer items-center justify-center rounded-md text-sm transition-colors',
        active ? 'bg-primary-soft text-primary' : 'text-muted hover:bg-surface-2 hover:text-foreground',
      )}
    >
      {children}
    </button>
  )
}

/**
 * Editor Rich Text baseado no TipTap. Reutilizável para posts, páginas, produtos, etc.
 */
export function RichTextEditor({
  value,
  onChange,
  label,
  hint,
  error,
  placeholder = 'Comece a escrever...',
  className,
}: RichTextEditorProps) {
  const [htmlMode, setHtmlMode] = useState(false)
  const [rawHtml, setRawHtml] = useState(value)
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] },
      }),
      ImageExtension,
      LinkExtension.configure({ openOnClick: false }),
      Placeholder.configure({ placeholder }),
    ],
    content: value,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML())
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none min-h-[14rem] px-4 py-3 text-foreground',
      },
    },
  })

  useEffect(() => {
    if (!editor) return
    if (htmlMode) return
    if (value === editor.getHTML()) return
    editor.commands.setContent(value)
  }, [value, editor, htmlMode])

  const toggleHtmlMode = useCallback(() => {
    if (!editor) return

    setHtmlMode((prev) => {
      if (prev) {
        editor.commands.setContent(rawHtml)
        return false
      }
      setRawHtml(editor.getHTML())
      return true
    })
  }, [editor, rawHtml])

  useEffect(() => {
    if (htmlMode) {
      textareaRef.current?.focus()
    }
  }, [htmlMode])

  const setLink = useCallback(() => {
    if (!editor) return

    const previousUrl = editor.getAttributes('link').href
    const url = window.prompt('URL do link:', previousUrl)

    if (url === null) return

    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()

      return
    }

    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
  }, [editor])

  const handleRawChange = (html: string) => {
    setRawHtml(html)
    onChange(html)
  }

  if (!editor) return null

  return (
    <Field label={label} hint={hint} error={error} className={className}>
      <div className={cn('overflow-hidden rounded-xl shadow-[inset_0_1px_0_var(--app-surface-2)]', error && 'outline-2 outline-danger/60')}>
        <div className="flex flex-wrap items-center gap-0.5 border-b border-surface-3 bg-surface-2/60 px-2 py-1.5">
          {htmlMode ? (
            <ToolbarButton
              active
              title="Editor visual"
              onClick={toggleHtmlMode}
            >
              <FileCode className="size-4" />
            </ToolbarButton>
          ) : (
            <>
              <ToolbarButton
                title="Negrito"
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
              >
                <Bold className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Itálico"
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
              >
                <Italic className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Título"
                active={editor.isActive('heading', { level: 2 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
              >
                <Heading2 className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Lista"
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
              >
                <List className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Lista numerada"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
              >
                <ListOrdered className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Citação"
                active={editor.isActive('blockquote')}
                onClick={() => editor.chain().focus().toggleBlockquote().run()}
              >
                <Quote className="size-4" />
              </ToolbarButton>
              <ToolbarButton
                title="Código"
                active={editor.isActive('codeBlock')}
                onClick={() => editor.chain().focus().toggleCodeBlock().run()}
              >
                <Code2 className="size-4" />
              </ToolbarButton>
              <ToolbarButton title="Link" onClick={setLink}>
                <Link2 className="size-4" />
              </ToolbarButton>
              <span className="mx-1 h-4 w-px bg-surface-3" aria-hidden="true" />
              <ToolbarButton title="Desfazer" onClick={() => editor.chain().focus().undo().run()}>
                <Undo2 className="size-4" />
              </ToolbarButton>
              <ToolbarButton title="Refazer" onClick={() => editor.chain().focus().redo().run()}>
                <Redo2 className="size-4" />
              </ToolbarButton>
              <span className="mx-1 h-4 w-px bg-surface-3" aria-hidden="true" />
              <ToolbarButton
                title="Editar HTML"
                active={htmlMode}
                onClick={toggleHtmlMode}
              >
                <FileCode className="size-4" />
              </ToolbarButton>
            </>
          )}
        </div>
        {htmlMode ? (
          <textarea
            ref={textareaRef}
            value={rawHtml}
            onChange={(e) => handleRawChange(e.target.value)}
            className="min-h-[14rem] w-full resize-y px-4 py-3 font-mono text-sm text-foreground focus:outline-none"
            placeholder={placeholder}
            spellCheck={false}
          />
        ) : (
          <EditorContent editor={editor} />
        )}
      </div>
    </Field>
  )
}
