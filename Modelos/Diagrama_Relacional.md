```mermaid

erDiagram
	direction TB
	ROLES {
		int id_rol PK ""  
		string descripcion  ""  
	}

	USUARIOS {
		int id_usuario PK ""  
		string username  ""  
		string contrasena  ""  
		int id_rol FK ""  
	}

	ESPECIALIDADES {
		int id_especialidad PK ""  
		string nombre  ""  
	}

	MEDICO_ESPECIALIDAD {
		int id_medico FK ""  
		int id_especialidad FK ""  
	}

	TIPO_SANGRE {
		int id_tipo_sangre PK ""  
		string descripcion  ""  
	}

	EXPEDIENTES {
		int id_expediente PK ""  
		int id_paciente FK ""  
		int id_tipo_sangre FK ""  
		date fecha_apertura  ""  
		float peso_actual  ""  
		float altura  ""  
		string observaciones_generales  ""  
	}

	ALERGIAS {
		int id_alergia PK ""  
		string descripcion  ""  
	}

	EXPEDIENTE_ALERGIA {
		int id_expediente FK ""  
		int id_alergia FK ""  
	}

	ENFERMEDADES_CRONICAS {
		int id_enfermedad PK ""  
		string descripcion  ""  
	}

	EXPEDIENTE_ENFERMEDAD {
		int id_expediente FK ""  
		int id_enfermedad FK ""  
	}

	TIPO_CONSULTA {
		int id_tipo_consulta PK ""  
		string descripcion  ""  
		float precio  ""  
	}

	CONSULTORIOS {
		int id_consultorio PK ""  
		string numero  ""  
		string piso  ""  
	}

	CONSULTAS {
		int id_consulta PK ""  
		int id_cita FK ""  
		int id_tipo_consulta FK ""  
		int id_consultorio FK ""  
		string observaciones  ""  
	}

	RECETAS {
		int id_receta PK ""  
		int id_consulta FK ""  
	}

	MEDICAMENTOS {
		int id_medicamento PK ""  
		string nombre  ""  
		string descripcion  ""  
		float precio  ""  
		int stock  ""  
		date fecha_caducidad  ""  
	}

	RECETA_MEDICAMENTO {
		int id_receta FK ""  
		int id_medicamento FK ""  
		string frecuencia  ""  
		string duracion  ""  
	}

	TIPO_MOVIMIENTO {
		int id_tipo PK ""  
		string descripcion  ""  
	}

	PACIENTES {
		int id_paciente PK ""  
		int id_usuario FK ""  
		string nombre  ""  
		string apellido_paterno  ""  
		string apellido_materno  ""  
		date fecha_nacimiento  ""  
		int edad  ""  
		string sexo  ""  
		string telefono  ""  
		string correo  ""  
	}

	MEDICOS {
		int id_medico PK ""  
		int id_usuario FK ""  
		string nombre  ""  
		string apellido_paterno  ""  
		string apellido_materno  ""  
		string cedula  ""  
		date fecha_nacimiento  ""  
		int edad  ""  
		string telefono  ""  
		string correo  ""  
	}

	CITAS {
		int id_cita PK ""  
		int id_paciente FK ""  
		int id_medico FK ""  
		int id_consultorio FK ""  
		date fecha  ""  
		time hora  ""  
		string estado  ""  
	}

	PAGOS {
		int id_pago PK ""  
		int id_consulta FK ""  
		float monto_total  ""  
		float subtotal  ""  
		string estado  ""  
		date fecha  ""  
	}

	MOVIMIENTO_MEDICAMENTO {
		int id_movimiento PK ""  
		int id_medicamento FK ""  
		int id_tipo FK ""  
		int cantidad  ""  
		date fecha  ""  
		time hora  ""  
	}

	PAGOS_MEDICAMENTOS {
		int id_pago FK ""  
		int id_medicamento FK ""  
	}

	ROLES||--|{USUARIOS:"tiene"
	USUARIOS||--o|MEDICOS:"perfil_de"
	USUARIOS||--o|PACIENTES:"perfil_de"
	MEDICOS||--o{MEDICO_ESPECIALIDAD:"posee"
	ESPECIALIDADES||--o{MEDICO_ESPECIALIDAD:"clasifica"
	PACIENTES||--|{EXPEDIENTES:"tiene"
	TIPO_SANGRE||--o{EXPEDIENTES:"define"
	EXPEDIENTES||--o{EXPEDIENTE_ALERGIA:"registra"
	ALERGIAS||--o{EXPEDIENTE_ALERGIA:"es_padecida"
	EXPEDIENTES||--o{EXPEDIENTE_ENFERMEDAD:"registra"
	ENFERMEDADES_CRONICAS||--o{EXPEDIENTE_ENFERMEDAD:"es_padecida"
	PACIENTES||--o{CITAS:"agenda"
	MEDICOS||--o{CITAS:"atiende"
	CITAS||--||CONSULTAS:"genera"
	TIPO_CONSULTA||--o{CONSULTAS:"categoriza"
	CONSULTAS||--||PAGOS:"genera"
	CONSULTAS||--o|RECETAS:"produce"
	RECETAS||--o{RECETA_MEDICAMENTO:"incluye"
	MEDICAMENTOS||--o{RECETA_MEDICAMENTO:"es_prescrito"
	MEDICAMENTOS||--|{MOVIMIENTO_MEDICAMENTO:"sufre"
	TIPO_MOVIMIENTO||--|{MOVIMIENTO_MEDICAMENTO:"tipifica"
	CONSULTORIOS||--o{CITAS:"  "
	PAGOS||--o{PAGOS_MEDICAMENTOS:"  "
	PAGOS_MEDICAMENTOS}o--||MEDICAMENTOS:"  "
