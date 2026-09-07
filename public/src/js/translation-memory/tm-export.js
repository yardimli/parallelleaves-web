// Keep the prompt text verbatim inside valid XML, including language labels
// such as "English (US)" which cannot be used as XML element names.
export function buildMemoryXml(entries, book) {
	const prompt = entries.map(entry =>
		`<${book.source_language}>${entry.source_sentence ?? ''}</${book.source_language}>\n<${book.target_language}>${entry.edited_target_sentence ?? ''}</${book.target_language}>\n`
	).join('').trim();
	return `<?xml version="1.0" encoding="UTF-8"?>\n<translation_memory><![CDATA[${prompt.replaceAll(']]>', ']]]]><![CDATA[>')}]]></translation_memory>\n`;
}
