const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');

// Helper to safely resolve relative file paths
function resolvePath(filePath) {
  if (fs.existsSync(filePath)) return filePath;
  const generatePath = path.join('dumps', 'generate', path.basename(filePath));
  if (fs.existsSync(generatePath)) return generatePath;
  const dumpsPath = path.join('dumps', path.basename(filePath));
  if (fs.existsSync(dumpsPath)) return dumpsPath;

  return filePath;
}

// -------------------------------------------------------------
// 1. Load Existing Seeder Files for Foreign Key Lookups
// -------------------------------------------------------------
const divisions = JSON.parse(fs.readFileSync(resolvePath('divisions.json'), 'utf8'));
const sectionsOrUnits = JSON.parse(fs.readFileSync(resolvePath('sections_or_units.json'), 'utf8'));
const programs = JSON.parse(fs.readFileSync(resolvePath('programs.json'), 'utf8'));
const offices = JSON.parse(fs.readFileSync(resolvePath('offices.json'), 'utf8'));
const salaryGrades = JSON.parse(fs.readFileSync(resolvePath('salary_grades_04292025.json'), 'utf8'));
const positions = JSON.parse(fs.readFileSync(resolvePath('positions.json'), 'utf8'));
const fundSources = JSON.parse(fs.readFileSync(resolvePath('fund-sources.json'), 'utf8'));

// -------------------------------------------------------------
// 2. Build Sets of Valid Foreign Key Primary Keys (IDs)
// -------------------------------------------------------------
const validDivisionIds = new Set(divisions.map(d => Number(d.id)));
const validSectionIds = new Set(sectionsOrUnits.map(s => Number(s.id)));
const validProgramIds = new Set(programs.map(p => Number(p.id)));
const validOfficeIds = new Set(offices.map(o => Number(o.id)));
const validSalaryGradeIds = new Set(salaryGrades.map(sg => Number(sg.id)));
const validPositionIds = new Set(positions.map(p => Number(p.id)));
const validFundSourceIds = new Set(fundSources.map(f => Number(f.id)));

// Fallback office ID in case an Excel office cell is blank
const defaultOfficeId = offices.length > 0 ? Number(offices[0].id) : 1;

// -------------------------------------------------------------
// 3. Build Lookup Maps for Foreign Keys
// -------------------------------------------------------------
const divisionMap = new Map(divisions.map(d => [String(d.name || d.title).trim().toLowerCase(), d.id]));
const sectionOrUnitMap = new Map(sectionsOrUnits.map(s => [String(s.name || s.title).trim().toLowerCase(), s.id]));
const programMap = new Map(programs.map(p => [String(p.name || p.code || p.title).trim().toLowerCase(), p.id]));

// Index all 152 offices across every available name field (name, title, code, acronym)
const officeMap = new Map();
const officeList = [];

offices.forEach(o => {
  const id = Number(o.id);
  const keys = [o.name, o.title, o.code, o.acronym, o.short_name]
    .filter(Boolean)
    .map(v => String(v).trim().toLowerCase());

  keys.forEach(k => officeMap.set(k, id));
  officeList.push({ id, keys });
});

/**
 * Flexible Office Resolver matching Excel strings against offices.json
 */
function resolveOfficeId(offRaw) {
  if (!offRaw) return null;
  const clean = String(offRaw).trim().toLowerCase();
  if (!clean) return null;

  // 1. Exact Match
  if (officeMap.has(clean)) return officeMap.get(clean);

  const sanitized = clean.replace(/[^a-z0-9\s]/gi, ' ').replace(/\s+/g, ' ').trim();
  if (officeMap.has(sanitized)) return officeMap.get(sanitized);

  // 2. Substring & Inclusion Matching
  for (const item of officeList) {
    for (const key of item.keys) {
      const sanitizedKey = key.replace(/[^a-z0-9\s]/gi, ' ').replace(/\s+/g, ' ').trim();
      if (sanitized === sanitizedKey || sanitized.includes(sanitizedKey) || sanitizedKey.includes(sanitized)) {
        return item.id;
      }
    }
  }

  // 3. Common Acronym/Prefix Rule Matching
  if (sanitized.includes('regionwide')) return officeMap.get('regionwide') || null;
  if (sanitized.includes('fo main') || sanitized.includes('main')) return officeMap.get('fo main') || officeMap.get('field office main') || null;
  if (sanitized.includes('rpmo') || sanitized.includes('4ps')) return officeMap.get('rpmo') || officeMap.get('regional program management office') || null;
  if (sanitized.includes('poo')) return officeMap.get('poo') || officeMap.get('provincial operations office') || null;

  return null;
}

const salaryGradeMap = new Map(salaryGrades.map(sg => {
  const rawKey = String(sg.grade || sg.salary_grade || sg.number || sg.name || sg.id).trim().toLowerCase();
  return [rawKey, sg.id];
}));

const positionMap = new Map(positions.map(p => [String(p.title || p.name).trim().toLowerCase(), p.id]));
const fundSourceMap = new Map(fundSources.map(f => [String(f.name || f.code).trim().toLowerCase(), f.id]));

function cleanString(val) {
  if (val === null || val === undefined) return "";
  return String(val)
    .replace(/[\r\n\t]/g, ' ')
    .replace(/[^\x20-\x7E]/g, '')
    .trim();
}

function formatDate(dateVal) {
  const DEFAULT_DATE = "2020-01-01";
  if (!dateVal) return DEFAULT_DATE;

  if (typeof dateVal === 'number') {
    const parsedDate = XLSX.SSF.parse_date_code(dateVal);
    if (parsedDate) {
      const y = parsedDate.y;
      const m = String(parsedDate.m).padStart(2, '0');
      const d = String(parsedDate.d).padStart(2, '0');
      return `${y}-${m}-${d}`;
    }
  }

  const str = cleanString(dateVal);
  if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
  if (/^\d{4}$/.test(str)) return `${str}-01-01`;

  const yearMatch = str.match(/\b(19\d{2}|20\d{2})\b/);
  if (yearMatch) return `${yearMatch[1]}-01-01`;

  return DEFAULT_DATE;
}

function validateFkId(entityName, rawValue, mappedId, validSet, fallbackValue = null) {
  if (mappedId !== null && mappedId !== undefined) {
    if (validSet.has(Number(mappedId))) return Number(mappedId);
  }
  return fallbackValue;
}

// -------------------------------------------------------------
// 4. Header & Matrix Extraction
// -------------------------------------------------------------
const workbook = XLSX.readFile('employee_data.xlsx');
const sheetName = workbook.SheetNames[0];
const sheet = workbook.Sheets[sheetName];

const range = XLSX.utils.decode_range(sheet['!ref']);

const rawMatrix = [];
for (let R = range.s.r; R <= range.e.r; ++R) {
  const rowArr = [];
  for (let C = range.s.c; C <= range.e.c; ++C) {
    const cellAddress = XLSX.utils.encode_cell({ r: R, c: C });
    const cell = sheet[cellAddress];
    rowArr.push(cell ? cleanString(cell.v) : "");
  }
  rawMatrix.push(rowArr);
}

// Find main header row index
let mainHeaderRowIdx = 0;
for (let i = 0; i < Math.min(20, rawMatrix.length); i++) {
  const combinedStr = rawMatrix[i].map(c => c.toUpperCase()).join(' ');
  if (combinedStr.includes("DIVISION") && combinedStr.includes("POSITION TITLE")) {
    mainHeaderRowIdx = i;
    break;
  }
}

// Unified multi-row headers for general columns
const unifiedHeaders = [];
const numCols = range.e.c - range.s.c + 1;

for (let c = 0; c < numCols; c++) {
  let stackedText = "";
  for (let r = 0; r <= mainHeaderRowIdx + 2; r++) {
    if (rawMatrix[r] && rawMatrix[r][c]) {
      stackedText += " " + rawMatrix[r][c].toUpperCase();
    }
  }
  unifiedHeaders.push(stackedText.replace(/\s+/g, ' ').trim());
}

function findColIdx(keywords) {
  return unifiedHeaders.findIndex(header => keywords.some(kw => header.includes(kw.toUpperCase())));
}

// General Column Maps
const idxDiv = findColIdx(["DIVISION"]);
const idxSec = findColIdx(["SECTION/UNIT", "SECTION"]);
const idxProg = findColIdx(["PROGRAM"]);
const idxEmpClass = findColIdx(["CLASSIFICATION OF EMPLOYMENT"]);
const idxFund = findColIdx(["FUND SOURCE"]);
const idxSg = findColIdx(["SALARY GRADE"]);
const idxPos = findColIdx(["POSITION TITLE"]);
const idxItemNum = findColIdx(["ITEM NUMBER"]);
const idxDateCreation = findColIdx(["DATE OF CREATION"]);
const idxItemStatus = findColIdx(["ITEM STATUS"]);
const idxLastName = findColIdx(["LAST NAME"]);
const idxFirstName = findColIdx(["FIRST NAME"]);

// Specific Row Target for OFFICE to prevent merged header mismatch
let idxOff = -1;
for (let r = 0; r <= mainHeaderRowIdx + 2; r++) {
  if (!rawMatrix[r]) continue;
  const colIndex = rawMatrix[r].findIndex(cell => cell.toUpperCase().trim() === "OFFICE");
  if (colIndex !== -1) {
    idxOff = colIndex;
    break;
  }
}

// Fallback to fuzzy header if exact cell "OFFICE" wasn't hit
if (idxOff === -1) {
  idxOff = findColIdx(["OFFICE"]);
}

console.log(`📌 Identified OFFICE Column Index: ${idxOff}`);

const seenItemNumbers = new Map();
const duplicatesList = [];
const validOutputRows = [];
const unmatchedOffices = new Set();
let unnumberedCounter = 1;

// -------------------------------------------------------------
// 5. Parse Data Rows
// -------------------------------------------------------------
const dataRows = rawMatrix.slice(mainHeaderRowIdx + 2);

dataRows.forEach((rowArray, index) => {
  const excelRowNumber = index + mainHeaderRowIdx + 3;

  const getVal = (idx) => (idx !== -1 && idx < rowArray.length && rowArray[idx] !== undefined) ? rowArray[idx] : "";

  const itemNumberRaw = getVal(idxItemNum);
  const posRaw = getVal(idxPos);
  const lastNameRaw = getVal(idxLastName);
  const firstNameRaw = getVal(idxFirstName);

  if (itemNumberRaw.toUpperCase().includes("ITEM NUMBER") || posRaw.toUpperCase().includes("POSITION TITLE")) return;

  const hasData = rowArray.some(val => val !== "");
  if (!hasData) return;

  let itemNumber = itemNumberRaw ? itemNumberRaw : `VACANT-ITEM-${unnumberedCounter++}`;

  const divRaw = getVal(idxDiv);
  const secRaw = getVal(idxSec);
  const progRaw = getVal(idxProg);
  const offRaw = getVal(idxOff);
  const sgRaw = getVal(idxSg);
  const fundRaw = getVal(idxFund);

  const sgCleanNum = sgRaw.toLowerCase().replace(/[^0-9]/g, '');

  let rawDivId = divisionMap.get(divRaw.toLowerCase()) || null;
  let rawSecId = sectionOrUnitMap.get(secRaw.toLowerCase()) || null;
  let rawProgId = programMap.get(progRaw.toLowerCase()) || null;
  let rawOffId = resolveOfficeId(offRaw);

  if (!rawOffId && offRaw) {
    unmatchedOffices.add(offRaw);
  }

  let rawSgId = salaryGradeMap.get(sgRaw.toLowerCase()) || salaryGradeMap.get(sgCleanNum) || (sgCleanNum ? Number(sgCleanNum) : 1);
  let rawPosId = positionMap.get(posRaw.toLowerCase()) || null;
  let rawFundId = fundSourceMap.get(fundRaw.toLowerCase()) || 1;

  const matchedDivId = validateFkId("Division", divRaw, rawDivId, validDivisionIds, null);
  const matchedSecId = validateFkId("Section/Unit", secRaw, rawSecId, validSectionIds, null);
  const matchedProgId = validateFkId("Program", progRaw, rawProgId, validProgramIds, null);
  const matchedOffId = validateFkId("Office", offRaw, rawOffId, validOfficeIds, null);

  const matchedSgId = validateFkId("Salary Grade", sgRaw, rawSgId, validSalaryGradeIds, 1) ?? 1;
  const matchedPosId = validateFkId("Position", posRaw, rawPosId, validPositionIds, 1) ?? 1;
  const matchedFundId = validateFkId("Fund Source", fundRaw, rawFundId, validFundSourceIds, 1) ?? 1;

  const empStatus = getVal(idxEmpClass) || "Permanent";
  const dateCreation = formatDate(getVal(idxDateCreation));

  let rowStatus = "Unfilled";
  let statusVal = getVal(idxItemStatus).toUpperCase();

  if (statusVal.includes("UNFILLED") || statusVal.includes("VACANT") || statusVal === "U") {
    rowStatus = "Unfilled";
  } else if (statusVal.includes("FILLED") || statusVal.includes("OCCUPIED") || statusVal === "F") {
    rowStatus = "Filled";
  } else {
    const hasEmployee = (lastNameRaw && lastNameRaw.trim() !== "") || (firstNameRaw && firstNameRaw.trim() !== "");
    if (hasEmployee && !lastNameRaw.toUpperCase().includes("VACANT") && !lastNameRaw.toUpperCase().includes("UNFILLED")) {
      rowStatus = "Filled";
    } else {
      rowStatus = "Unfilled";
    }
  }

  if (seenItemNumbers.has(itemNumber)) {
    duplicatesList.push({
      itemNumber: itemNumber,
      originalExcelRow: seenItemNumbers.get(itemNumber),
      duplicateExcelRow: excelRowNumber
    });
  } else {
    seenItemNumbers.set(itemNumber, excelRowNumber);
  }

  validOutputRows.push({
    id: validOutputRows.length + 1,
    division_id: matchedDivId,
    section_or_unit_id: matchedSecId,
    program_id: matchedProgId,
    office_id: matchedOffId,
    psipop_id: null,
    number: itemNumber,
    date_of_creation: dateCreation,
    designation: null,
    date_of_designation: null,
    special_order_number: null,
    status: rowStatus,
    mode_of_accession: null,
    date_filled_up: null,
    history_of_position: null,
    former_incumbent: null,
    mode_of_separation: null,
    date_of_vacant: null,
    remarks_of_vacancy: null,
    status_of_vacant_position: null,
    direct_contact_exposure_with_client: null,
    remarks: null,
    employment_status: empStatus,
    salary_grade_id: matchedSgId,
    position_id: matchedPosId,
    item_classification: null,
    fund_source_id: matchedFundId
  });
});

// -------------------------------------------------------------
// 6. Save Output & Print Summary
// -------------------------------------------------------------
fs.writeFileSync('items.json', JSON.stringify(validOutputRows, null, 2));

const filledCount = validOutputRows.filter(r => r.status === "Filled").length;
const unfilledCount = validOutputRows.filter(r => r.status === "Unfilled").length;
const mappedOfficesCount = validOutputRows.filter(r => r.office_id !== null).length;

console.log(`-------------------------------------------------------------`);
console.log(`🎉 Exported ${validOutputRows.length} total records to 'items.json'.`);
console.log(`📊 STATS:`);
console.log(`   • Filled Positions:   ${filledCount}`);
console.log(`   • Unfilled Positions: ${unfilledCount}`);
console.log(`   • Mapped Office IDs:  ${mappedOfficesCount} / ${validOutputRows.length}`);

if (unmatchedOffices.size > 0) {
  console.log(`\n⚠️  The following Office strings in Excel could not be matched to offices.json:`);
  unmatchedOffices.forEach(str => console.log(`   - "${str}"`));
}

console.log(`-------------------------------------------------------------\n`);