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
                name: "{{ .Release.Name }}-s3"
                key: "accesskey"
          - name: "AWS_BUCKET"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-s3"
                key: "bucket"
          - name: "AWS_REGION"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-s3"
                key: "region"
          - name: "AWS_SECRET_ACCESS_KEY"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-s3"
                key: "secretkey"
          - name: "DB_CONNECTION"
            value: "pgsql"
          - name: "DB_DATABASE"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-db"
                key: "database"
          - name: "DB_HOST"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-db"
                key: "host"
          - name: "DB_PORT"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-db"
                key: "port"
          - name: "DB_USERNAME"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-db"
                key: "username"
          - name: "DB_PASSWORD"
            valueFrom:
              secretKeyRef:
                name: "{{ .Release.Name }}-db"
                key: "password"
          envFrom:
            - configMapRef:
                name: "{{ .Release.Name }}-website"
{{- end }}
