# Assay Protocol — CX-4471 Hepatotoxicity Panel (NAM-STUDY-001)

> POC demo metadata for the liver-organoid hepatotoxicity use case. Not a GLP protocol.

## Biological system
- iPSC-derived liver organoids (hepatocyte-like cells), 3D Matrigel-embedded domes.
- Three donor lines; maturity confirmed by albumin secretion and CYP3A4 induction.

## Endpoints
| Endpoint | Method | Readout | Unit | Ontology |
|----------|--------|---------|------|----------|
| ATP viability | CellTiter-Glo 3D | luminescence | % vehicle control | OBI:0002119 |
| LDH release | CytoTox-ONE | fluorescence | % max lysis | OBI:0002994 |
| Mitochondrial membrane potential | JC-1 | ratiometric fluorescence | J-aggregate:monomer ratio | NCIT:C17610 |
| Intracellular bile acid | LC-MS/MS | peak area ratio | fold over vehicle | NCIT:C154834 |
| ROS generation | CellROX Green | fluorescence | fold over vehicle | CHEBI:26523 |
| Lipid peroxidation index | exploratory panel | fluorescence | fold over vehicle | *(unmapped — see demo issues)* |

## Design
- Concentration-response with vehicle control; 24 h and 72 h timepoints.
- n = 3 biological replicates × 3 technical replicates.
- Reference compounds: acetaminophen (positive), metformin (negative control).

## Acquisition & analysis
- Plate reader: EnVision 2105; LC-MS/MS: Agilent 6545 QTOF.
- Analysis pipeline: see `analysis_script_reference.txt`.
