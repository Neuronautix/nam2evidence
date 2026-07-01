"""NAM-CORE validator sidecar.

A small Flask service that runs SHACL validation over a NAM-CORE JSON-LD graph
and converts flat table rows into Parquet files packaged as a ZIP. This is a
proof-of-concept sidecar for the NAM-CORE toolkit — NOT an official validator.
"""

import io
import os
import zipfile

import pyarrow as pa
import pyarrow.parquet as pq
from flask import Flask, Response, jsonify, request, send_file
from pyshacl import validate
from rdflib import Graph
from rdflib.namespace import SH

app = Flask(__name__)

BUNDLED_SHAPES_PATH = os.environ.get(
    "NAM_CORE_SHAPES_PATH", "/app/shapes/nam-core-v0.1.ttl"
)


def _term_str(value):
    """Return a string for an rdflib term, or None if absent."""
    if value is None:
        return None
    return str(value)


def _severity_label(severity_term):
    """Map a sh:severity term to 'Violation' or 'Warning'."""
    if severity_term == SH.Warning:
        return "Warning"
    # Treat Violation (and anything else, e.g. Info) as a blocking Violation by
    # default so nothing is silently dropped.
    return "Violation"


def _normalize_report(results_graph):
    """Turn a SHACL results graph into a normalized JSON report."""
    results = []
    violation_count = 0
    warning_count = 0

    for result in results_graph.subjects(SH.resultSeverity, None):
        severity_term = results_graph.value(result, SH.resultSeverity)
        severity = _severity_label(severity_term)

        focus_node = results_graph.value(result, SH.focusNode)
        path = results_graph.value(result, SH.resultPath)
        message = results_graph.value(result, SH.resultMessage)
        source_shape = results_graph.value(result, SH.sourceShape)
        value = results_graph.value(result, SH.value)

        if severity == "Warning":
            warning_count += 1
        else:
            violation_count += 1

        results.append(
            {
                "severity": severity,
                "focus_node": _term_str(focus_node),
                "path": _term_str(path),
                "message": _term_str(message) if message is not None else "",
                "source_shape": _term_str(source_shape),
                "value": _term_str(value),
            }
        )

    return results, violation_count, warning_count


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "nam-core-validator"})


@app.route("/validate", methods=["POST"])
def validate_graph():
    payload = request.get_json(silent=True)
    if payload is None or not isinstance(payload, dict):
        return jsonify({"error": "Request body must be a JSON object."}), 400

    jsonld = payload.get("jsonld")
    if jsonld is None:
        return jsonify({"error": "Missing 'jsonld' in request body."}), 400

    data_graph = Graph()
    try:
        data_graph.parse(data=jsonld, format="json-ld")
    except Exception as exc:  # noqa: BLE001 - report any parse failure to caller
        return jsonify({"error": f"Failed to parse JSON-LD: {exc}"}), 400

    shapes_ttl = payload.get("shapes_ttl")
    shapes_graph = Graph()
    try:
        if shapes_ttl:
            shapes_graph.parse(data=shapes_ttl, format="turtle")
        else:
            shapes_graph.parse(BUNDLED_SHAPES_PATH, format="turtle")
    except Exception as exc:  # noqa: BLE001
        return jsonify({"error": f"Failed to load SHACL shapes: {exc}"}), 400

    try:
        conforms, results_graph, _text = validate(
            data_graph,
            shacl_graph=shapes_graph,
            inference="rdfs",
            advanced=True,
        )
    except Exception as exc:  # noqa: BLE001
        return jsonify({"error": f"Validation failed: {exc}"}), 400

    results, violation_count, warning_count = _normalize_report(results_graph)

    return jsonify(
        {
            "conforms": bool(conforms),
            "violation_count": violation_count,
            "warning_count": warning_count,
            "results": results,
        }
    )


def _table_from_rows(rows):
    """Build a pyarrow Table from ragged flat dict rows.

    The union of all keys becomes the column set; missing values are None.
    """
    columns = []
    for row in rows:
        for key in row.keys():
            if key not in columns:
                columns.append(key)

    data = {col: [row.get(col) for row in rows] for col in columns}
    return pa.table(data)


@app.route("/parquet", methods=["POST"])
def parquet():
    payload = request.get_json(silent=True)
    if payload is None or not isinstance(payload, dict):
        return jsonify({"error": "Request body must be a JSON object."}), 400

    tables = payload.get("tables")
    if not isinstance(tables, dict):
        return jsonify({"error": "Missing 'tables' object in request body."}), 400

    buffer = io.BytesIO()
    written = 0
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as zf:
        for name, rows in tables.items():
            if not isinstance(rows, list) or len(rows) == 0:
                continue
            try:
                table = _table_from_rows(rows)
                table_buffer = io.BytesIO()
                pq.write_table(table, table_buffer)
            except Exception as exc:  # noqa: BLE001
                return (
                    jsonify({"error": f"Failed to build parquet for '{name}': {exc}"}),
                    400,
                )
            zf.writestr(f"{name}.parquet", table_buffer.getvalue())
            written += 1

    if written == 0:
        return jsonify({"error": "No non-empty tables provided."}), 400

    buffer.seek(0)
    return send_file(
        buffer,
        mimetype="application/zip",
        as_attachment=True,
        download_name="nam-core-parquet.zip",
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000)
