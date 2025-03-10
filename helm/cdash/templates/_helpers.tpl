{{/*
Returns the CDash URL, ex: `http://cdash-example.local`
Use https if `cdash.https` is true, otherwise use http.
*/}}
{{- define "cdash.url" -}}
{{- if .Values.cdash.https -}}
{{-   printf "https://%s" $.Values.cdash.host -}}
{{- else -}}
{{-   printf "http://%s" $.Values.cdash.host -}}
{{- end -}}
{{- end -}}

{{- define "cdash.environment" }}
          - name: "APP_KEY"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "APP_KEY"
          - name: "AWS_ACCESS_KEY_ID"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "AWS_ACCESS_KEY_ID"
          - name: "AWS_SECRET_ACCESS_KEY"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "AWS_SECRET_ACCESS_KEY"
          - name: "DB_CONNECTION"
            value: "pgsql"
          - name: "DB_DATABASE"
            value: "cdash"
          - name: "DB_PORT"
            value: "5432"
          {{ if .Values.postgresql.enabled }}
          - name: "DB_HOST"
            value: "{{ .Release.Name }}-postgresql"
          - name: "DB_USERNAME"
            value: "postgres"
          - name: "DB_PASSWORD"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-website"
                key: "DB_PASSWORD"
          {{- else -}}
          - name: "DB_HOST"
            valueFrom:
              secretKeyRef:
                name: "cdash-database"
                key: "host"
          - name: "DB_USERNAME"
            valueFrom:
              secretKeyRef:
                name: "cdash-database"
                key: "username"
          - name: "DB_PASSWORD"
            valueFrom:
              secretKeyRef:
                name: "cdash-database"
                key: "password"
          {{- end }}
          envFrom:
            - configMapRef:
                name: "{{ .Release.Name }}-website"
{{- end }}
