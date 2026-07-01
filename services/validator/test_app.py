"""Minimal tests for the NAM-CORE validator sidecar."""

import io
import json
import zipfile

import pytest

import app as app_module


@pytest.fixture
def client():
    app_module.app.config["TESTING"] = True
    return app_module.app.test_client()


def test_health(client):
    resp = client.get("/health")
    assert resp.status_code == 200
    body = resp.get_json()
    assert body["status"] == "ok"
    assert body["service"] == "nam-core-validator"


def test_validate_returns_report(client):
    # Tiny JSON-LD graph and matching shapes. The shape requires a name; the
    # single node has one, so we should get a conforming report either way —
    # the assertion only checks the report shape.
    jsonld = json.dumps(
        {
            "@context": {"ex": "http://example.org/"},
            "@id": "http://example.org/thing1",
            "@type": "ex:Thing",
            "ex:name": "hello",
        }
    )
    shapes_ttl = """
    @prefix sh: <http://www.w3.org/ns/shacl#> .
    @prefix ex: <http://example.org/> .
    @prefix xsd: <http://www.w3.org/2001/XMLSchema#> .

    ex:ThingShape a sh:NodeShape ;
        sh:targetClass ex:Thing ;
        sh:property [
            sh:path ex:name ;
            sh:minCount 1 ;
            sh:datatype xsd:string ;
            sh:severity sh:Violation ;
            sh:message "Thing must have a name." ;
        ] .
    """

    resp = client.post(
        "/validate",
        json={"jsonld": jsonld, "shapes_ttl": shapes_ttl},
    )
    assert resp.status_code == 200
    body = resp.get_json()
    assert "conforms" in body
    assert "violation_count" in body
    assert "warning_count" in body
    assert isinstance(body["results"], list)


def test_validate_bad_jsonld(client):
    resp = client.post(
        "/validate",
        json={"jsonld": "{ this is not valid json-ld", "shapes_ttl": ""},
    )
    assert resp.status_code == 400
    assert "error" in resp.get_json()


def test_parquet_returns_zip(client):
    resp = client.post(
        "/parquet",
        json={
            "tables": {
                "samples": [
                    {"id": 1, "label": "a"},
                    {"id": 2, "label": "b", "extra": "x"},
                ]
            }
        },
    )
    assert resp.status_code == 200
    assert resp.mimetype == "application/zip"
    data = resp.get_data()
    assert data[:2] == b"PK"
    with zipfile.ZipFile(io.BytesIO(data)) as zf:
        assert "samples.parquet" in zf.namelist()


def test_parquet_no_tables(client):
    resp = client.post("/parquet", json={"tables": {}})
    assert resp.status_code == 400
    assert "error" in resp.get_json()
