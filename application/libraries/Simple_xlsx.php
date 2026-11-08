<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Penulis XLSX minimal (multi-sheet, baris pertama sebagai header tebal) berbasis ZipArchive.
class Simple_xlsx
{
	public function build(array $sheets)
	{
		$path = tempnam(sys_get_temp_dir(), 'xlsx');
		$zip = new ZipArchive();
		if ($zip->open($path, ZipArchive::OVERWRITE) !== TRUE) {
			return FALSE;
		}
		$content = '';
		$rels = '';
		$workbook = '';
		$i = 0;
		foreach ($sheets as $name => $rows) {
			$i++;
			$content .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
			$workbook .= '<sheet name="'.$this->_esc(substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $name), 0, 31)).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
			$zip->addFromString('xl/worksheets/sheet'.$i.'.xml', $this->_sheet($rows));
		}
		$rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$content.'</Types>');
		$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
		$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbook.'</sheets></workbook>');
		$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
		$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
		$zip->close();
		return $path;
	}

	private function _sheet($rows)
	{
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		foreach (array_values($rows) as $r => $row) {
			$xml .= '<row r="'.($r + 1).'">';
			foreach (array_values($row) as $c => $value) {
				$ref = $this->_col($c).($r + 1);
				$style = $r === 0 ? ' s="1"' : '';
				if (is_int($value) || is_float($value)) {
					$xml .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
				} else {
					$xml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->_esc((string) $value).'</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		return $xml.'</sheetData></worksheet>';
	}

	private function _col($index)
	{
		$name = '';
		for ($n = $index; $n >= 0; $n = intval($n / 26) - 1) {
			$name = chr(65 + $n % 26).$name;
		}
		return $name;
	}

	private function _esc($value)
	{
		$value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
